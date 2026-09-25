<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Service;

use Morefoto\Payment\Application\Payment\Contract\PaymentProviderInterface;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Repository\PaymentFactRepositoryInterface;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use Morefoto\Payment\Domain\Payment\ValueObject\AttemptOutcome;
use Psr\Log\LoggerInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;

/**
 * Приводит открытую попытку к состоянию провайдера: создаёт платёж с сохранённым ключом идемпотентности или
 * запрашивает его статус, затем в одной транзакции фиксирует исход попытки, денежный факт и статус заказа.
 * Возврат браузера, уведомление и cron — только поводы для сверки; оплату доказывает ответ провайдера.
 *
 * @phpstan-import-type AttemptRecord from PaymentAttemptRepositoryInterface
 */
final readonly class PaymentReconciler
{
    public function __construct(
        private PaymentAttemptRepositoryInterface $attempts,
        private PaymentFactRepositoryInterface $facts,
        private PaymentProviderInterface $provider,
        private OrderPaymentInterface $orders,
        private PaymentTransactionInterface $transaction,
        private PaymentAttemptPolicy $policy,
        private PaymentSettings $settings,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /** @return null|AttemptStatusEnum статус после сверки; null — попытки нет */
    public function reconcile(int $attemptId, ConfirmationSourceEnum $source): ?AttemptStatusEnum
    {
        $attempt = $this->attempts->find($attemptId);
        if (null === $attempt || !AttemptStatusEnum::from($attempt['STATUS'])->isOpen()) {
            return null === $attempt ? null : AttemptStatusEnum::from($attempt['STATUS']);
        }
        $now = $this->clock->now();
        $payment = null;
        $rejected = false;
        // Provider HTTP never runs inside the SQL transaction.
        try {
            if (null !== $attempt['PROVIDER_PAYMENT_ID']) {
                $payment = $this->provider->find($attempt['PROVIDER_PAYMENT_ID']);
            } elseif ($this->policy->providerKeyUsable($attempt['CREATED_AT'], $now)) {
                $payment = $this->provider->create($this->createInput($attempt));
            }
        } catch (ProviderRejectedException $error) {
            // A rejected status request says nothing about the payment itself: only a rejected creation closes the attempt.
            $rejected = null === $attempt['PROVIDER_PAYMENT_ID'];
            $this->logger->error('Payment provider rejected the request.', ['attemptId' => $attempt['PUBLIC_ID'], 'error' => $error->getMessage()]);
        } catch (ProviderUnavailableException $error) {
            $this->logger->warning('Payment provider is unavailable.', ['attemptId' => $attempt['PUBLIC_ID'], 'error' => $error->getMessage()]);
        }

        return $this->transaction->execute(fn(): AttemptStatusEnum => $this->apply($attempt, $payment, $rejected, $source, $now));
    }

    /** @param AttemptRecord $seen */
    private function apply(array $seen, ?ProviderPaymentOutputDto $payment, bool $rejected, ConfirmationSourceEnum $source, \DateTimeImmutable $now): AttemptStatusEnum
    {
        // Lock order: the order first, then the attempt — the same order as starting a new attempt.
        $order = $this->orders->lock($seen['ORDER_ID']);
        $attempt = $this->attempts->lock($seen['ID']);
        if (null === $attempt) {
            return AttemptStatusEnum::from($seen['STATUS']);
        }
        $status = AttemptStatusEnum::from($attempt['STATUS']);
        if (!$status->isOpen()) {
            return $status;
        }
        $outcome = $this->outcome($attempt, $payment, $rejected, $order->closesAt, $now);
        $this->attempts->saveOutcome($attempt['ID'], $outcome);
        if (AttemptStatusEnum::SUCCEEDED === $outcome->status && null !== $outcome->paidAt && null !== $outcome->providerPaymentId) {
            $this->facts->insert([
                'ATTEMPT_ID' => $attempt['ID'],
                'ORDER_ID' => $attempt['ORDER_ID'],
                'PROVIDER' => $attempt['PROVIDER'],
                'PROVIDER_PAYMENT_ID' => $outcome->providerPaymentId,
                'AMOUNT' => $attempt['AMOUNT'],
                'INCOME_AMOUNT' => $outcome->incomeAmount,
                'PAID_AT' => $outcome->paidAt,
                'LATE_PAYMENT' => $outcome->latePayment,
                'CONFIRMED_BY' => $source->value,
                'CREATED_AT' => $outcome->checkedAt,
            ]);
            $this->orders->applyPayment(new OrderPaymentInputDto($attempt['ORDER_ID'], 'paid', $outcome->paidAt, $outcome->latePayment));
            $this->logger->info('Payment confirmed.', ['attemptId' => $attempt['PUBLIC_ID'], 'orderNumber' => $attempt['ORDER_NUMBER'], 'latePayment' => $outcome->latePayment, 'source' => $source->value]);
        } elseif (AttemptStatusEnum::CANCELED === $outcome->status) {
            $this->orders->applyPayment(new OrderPaymentInputDto($attempt['ORDER_ID'], 'declined'));
            $this->logger->info('Payment canceled.', ['attemptId' => $attempt['PUBLIC_ID'], 'orderNumber' => $attempt['ORDER_NUMBER'], 'reason' => $outcome->cancelReason]);
        }

        return $outcome->status;
    }

    /** @param AttemptRecord $attempt */
    private function outcome(array $attempt, ?ProviderPaymentOutputDto $payment, bool $rejected, ?string $closesAt, \DateTimeImmutable $now): AttemptOutcome
    {
        $checkedAt = $this->policy->utc($now);
        $checks = $attempt['CHECK_COUNT'] + 1;
        if ($rejected) {
            return new AttemptOutcome(AttemptStatusEnum::CANCELED, $checkedAt, null, cancelReason: 'provider_rejected');
        }
        $current = AttemptStatusEnum::from($attempt['STATUS']);
        if (null === $payment) {
            $keyExpired = null === $attempt['PROVIDER_PAYMENT_ID'] && !$this->policy->providerKeyUsable($attempt['CREATED_AT'], $now);

            return new AttemptOutcome(
                $current,
                $checkedAt,
                $keyExpired ? null : $this->policy->nextCheckAt($current, $checks, $now),
                $attempt['PROVIDER_PAYMENT_ID'],
                $attempt['CONFIRMATION_URL'],
                $keyExpired ? 'provider_key_expired' : null,
            );
        }
        $mismatch = $this->policy->mismatch($attempt['AMOUNT'], $attempt['SHOP_ID'], $attempt['PUBLIC_ID'], $payment->amount, $payment->currency, $payment->shopId, $payment->attemptId)
            ?? (null !== $attempt['PROVIDER_PAYMENT_ID'] && $payment->id !== $attempt['PROVIDER_PAYMENT_ID'] ? 'provider_payment_mismatch' : null);
        if (null !== $mismatch) {
            // A foreign or altered payment never pays the order; the attempt stays blocked for a manual decision.
            $this->logger->warning('Payment provider answer does not belong to the attempt.', ['attemptId' => $attempt['PUBLIC_ID'], 'reason' => $mismatch]);

            return new AttemptOutcome(AttemptStatusEnum::UNKNOWN, $checkedAt, null, $attempt['PROVIDER_PAYMENT_ID'], $attempt['CONFIRMATION_URL'], $mismatch);
        }
        $status = match ($payment->status) {
            'succeeded' => AttemptStatusEnum::SUCCEEDED,
            'canceled' => AttemptStatusEnum::CANCELED,
            default => AttemptStatusEnum::PENDING,
        };
        $paidAt = AttemptStatusEnum::SUCCEEDED === $status ? ($payment->paidAt ?? $checkedAt) : null;

        return new AttemptOutcome(
            status: $status,
            checkedAt: $checkedAt,
            nextCheckAt: $this->policy->nextCheckAt($status, $checks, $now),
            providerPaymentId: $payment->id,
            confirmationUrl: $payment->confirmationUrl ?? $attempt['CONFIRMATION_URL'],
            cancelReason: AttemptStatusEnum::CANCELED === $status ? ($payment->cancelReason ?? 'canceled') : null,
            paidAt: $paidAt,
            incomeAmount: null === $paidAt ? null : $payment->incomeAmount,
            latePayment: null !== $paidAt && $this->policy->isLate($closesAt, $paidAt),
        );
    }

    /** @param AttemptRecord $attempt */
    private function createInput(array $attempt): CreateProviderPaymentInputDto
    {
        return new CreateProviderPaymentInputDto(
            amount: $attempt['AMOUNT'],
            paymentMethod: $attempt['PAYMENT_METHOD'],
            description: 'Заказ ' . $attempt['ORDER_NUMBER'],
            returnUrl: $this->settings->returnUrl($attempt['PUBLIC_ID']),
            attemptId: $attempt['PUBLIC_ID'],
            orderId: $attempt['ORDER_PUBLIC_ID'],
            idempotenceKey: $attempt['PROVIDER_KEY'],
        );
    }
}
