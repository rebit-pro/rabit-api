<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Service;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderReceiptInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderReceiptOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptKindEnum;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptStatusEnum;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptPolicy;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptCheck;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/**
 * Доводит кассовый чек до итога провайдера (G2-DEC-08): чек оплаты находит среди чеков платежа, закрывающий создаёт
 * только после регистрации чека оплаты и затем запрашивает его статус. HTTP провайдера выполняется вне транзакции,
 * чек без итога за 72 часа уходит на ручную проверку, а ошибка чека никогда не меняет оплату.
 *
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final readonly class ReceiptReconciler
{
    public function __construct(
        private PaymentReceiptRepositoryInterface $receipts,
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentProviderInterface $provider,
        private PaymentTransactionInterface $transaction,
        private ReceiptSnapshotMapper $snapshots,
        private ReceiptPolicy $policy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return null|ReceiptStatusEnum статус после сверки; null — чека нет */
    public function reconcile(int $receiptId): ?ReceiptStatusEnum
    {
        $receipt = $this->receipts->find($receiptId);
        if (null === $receipt || !ReceiptStatusEnum::from($receipt['STATUS'])->isOpen()) {
            return null === $receipt ? null : ReceiptStatusEnum::from($receipt['STATUS']);
        }
        $now = $this->clock->now();
        $check = ReceiptKindEnum::PAYMENT->value === $receipt['KIND'] ? $this->payment($receipt, $now) : $this->closing($receipt, $now);

        return $this->transaction->execute(function() use ($receiptId, $check, $now): ReceiptStatusEnum {
            $current = $this->receipts->lock($receiptId) ?? throw new \LogicException('A payment receipt disappeared.');
            $status = ReceiptStatusEnum::from($current['STATUS']);
            if (!$status->isOpen()) {
                return $status;
            }
            if ($check->status->isOpen() && $this->policy->expired($current['CREATED_AT'], $now)) {
                $check = new ReceiptCheck(ReceiptStatusEnum::FAILED, $check->checkedAt, null, $check->providerReceiptId, 'timeout');
            }
            $this->receipts->save($receiptId, $check);
            $this->log($current, $check);

            return $check->status;
        });
    }

    /** @param ReceiptRecord $receipt */
    private function payment(array $receipt, \DateTimeImmutable $now): ReceiptCheck
    {
        try {
            foreach ($this->provider->paymentReceipts($receipt['PROVIDER_PAYMENT_ID']) as $found) {
                if ('payment' === $found->type) {
                    return $this->result($receipt, $found, $now);
                }
            }
        } catch (ProviderRejectedException|ProviderUnavailableException $error) {
            return $this->unavailable($receipt, $error, $now);
        }

        return $this->waiting($receipt, null, $now);
    }

    /** @param ReceiptRecord $receipt */
    private function closing(array $receipt, \DateTimeImmutable $now): ReceiptCheck
    {
        if (null !== $receipt['PROVIDER_RECEIPT_ID']) {
            try {
                return $this->result($receipt, $this->provider->findReceipt($receipt['PROVIDER_RECEIPT_ID']), $now);
            } catch (ProviderRejectedException|ProviderUnavailableException $error) {
                return $this->unavailable($receipt, $error, $now);
            }
        }
        $payment = null;
        foreach ($this->receipts->forOrder($receipt['ORDER_ID']) as $sibling) {
            if (ReceiptKindEnum::PAYMENT->value === $sibling['KIND'] && $sibling['ATTEMPT_ID'] === $receipt['ATTEMPT_ID']) {
                $payment = ReceiptStatusEnum::from($sibling['STATUS']);
            }
        }
        if (ReceiptStatusEnum::SUCCEEDED !== $payment) {
            // The prepayment must be registered before it is settled; a failed payment receipt needs a manual one.
            return null !== $payment && $payment->isOpen() ? $this->waiting($receipt, null, $now) : $this->failed('payment_receipt_failed', $now);
        }
        if (null !== $receipt['SENT_AT'] && !$this->policy->providerKeyUsable($receipt['SENT_AT'], $now)) {
            // A repeat with an expired key could register a second closing receipt.
            return $this->failed('provider_key_expired', $now);
        }
        $attempt = $this->attempts->find($receipt['ATTEMPT_ID']);
        if (null === $attempt || null === $attempt['RECEIPT']) {
            return $this->failed('payment_receipt_failed', $now);
        }
        $input = new CreateProviderReceiptInputDto(
            providerPaymentId: $receipt['PROVIDER_PAYMENT_ID'],
            receipt: new ReceiptSnapshot($this->snapshots->decode($attempt['RECEIPT'])->email, $this->snapshots->decodeItemsJson($receipt['ITEMS'])),
            prepayment: $receipt['AMOUNT'],
            idempotenceKey: $receipt['PROVIDER_KEY'],
        );
        $this->receipts->markSent($receipt['ID'], $this->policy->utc($now));
        try {
            return $this->result($receipt, $this->provider->createReceipt($input), $now);
        } catch (ProviderRejectedException $error) {
            $this->logger->error('Payment provider rejected the closing receipt.', ['receiptId' => $receipt['PUBLIC_ID'], 'error' => $error->getMessage()]);

            return $this->failed('provider_rejected', $now);
        } catch (ProviderUnavailableException $error) {
            return $this->unavailable([...$receipt, 'STATUS' => ReceiptStatusEnum::PENDING->value], $error, $now);
        }
    }

    /** @param ReceiptRecord $receipt */
    private function result(array $receipt, ProviderReceiptOutputDto $found, \DateTimeImmutable $now): ReceiptCheck
    {
        $status = match ($found->status) {
            'succeeded' => ReceiptStatusEnum::SUCCEEDED,
            'canceled' => ReceiptStatusEnum::CANCELED,
            default => ReceiptStatusEnum::PENDING,
        };

        return new ReceiptCheck(
            status: $status,
            checkedAt: $this->policy->utc($now),
            nextCheckAt: $status->isOpen() ? $this->policy->nextCheckAt($receipt['CHECK_COUNT'] + 1, $now) : null,
            providerReceiptId: $found->id,
            error: ReceiptStatusEnum::CANCELED === $status ? 'provider_canceled' : null,
            fiscalDocumentNumber: $found->fiscalDocumentNumber,
            fiscalStorageNumber: $found->fiscalStorageNumber,
            fiscalAttribute: $found->fiscalAttribute,
            registeredAt: $found->registeredAt,
        );
    }

    /** @param ReceiptRecord $receipt */
    private function waiting(array $receipt, ?string $error, \DateTimeImmutable $now): ReceiptCheck
    {
        return new ReceiptCheck(ReceiptStatusEnum::from($receipt['STATUS']), $this->policy->utc($now), $this->policy->nextCheckAt($receipt['CHECK_COUNT'] + 1, $now), error: $error);
    }

    /** @param ReceiptRecord $receipt */
    private function unavailable(array $receipt, \Throwable $error, \DateTimeImmutable $now): ReceiptCheck
    {
        $this->logger->warning('Payment provider receipt check failed.', ['receiptId' => $receipt['PUBLIC_ID'], 'error' => $error->getMessage()]);

        return $this->waiting($receipt, 'provider_unavailable', $now);
    }

    private function failed(string $error, \DateTimeImmutable $now): ReceiptCheck
    {
        return new ReceiptCheck(ReceiptStatusEnum::FAILED, $this->policy->utc($now), null, error: $error);
    }

    /** @param ReceiptRecord $receipt */
    private function log(array $receipt, ReceiptCheck $check): void
    {
        $context = ['receiptId' => $receipt['PUBLIC_ID'], 'kind' => $receipt['KIND'], 'error' => $check->error];
        match ($check->status) {
            ReceiptStatusEnum::SUCCEEDED => $this->logger->info('Receipt registered.', $context),
            ReceiptStatusEnum::CANCELED, ReceiptStatusEnum::FAILED => $this->logger->warning('Receipt needs a manual check.', $context),
            default => null,
        };
    }
}
