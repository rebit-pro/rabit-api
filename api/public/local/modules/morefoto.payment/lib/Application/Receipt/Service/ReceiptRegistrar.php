<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Service;

use Morefoto\Payment\Application\Payment\Contract\PaymentIdGeneratorInterface;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptKindEnum;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptStatusEnum;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptComposer;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptPolicy;

/**
 * Ставит кассовые чеки на сверку с провайдером: чек оплаты — когда провайдер подтвердил платёж попытки со снимком
 * чека, закрывающий чек печати — когда сотрудник отметил выдачу (G2-DEC-05). Повтор того же события второй чек не создаёт.
 *
 * @phpstan-import-type AttemptRecord from PaymentAttemptRepositoryInterface
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final readonly class ReceiptRegistrar
{
    public function __construct(
        private PaymentReceiptRepositoryInterface $receipts,
        private PaymentIdGeneratorInterface $ids,
        private ReceiptSnapshotMapper $snapshots,
        private ReceiptComposer $composer,
        private ReceiptPolicy $policy,
    ) {}

    /**
     * В транзакции подтверждения оплаты; попытка без снимка начата при выключенных чеках.
     *
     * @param AttemptRecord $attempt
     */
    public function paymentConfirmed(array $attempt, string $providerPaymentId, \DateTimeImmutable $now): void
    {
        if (null === $attempt['RECEIPT']) {
            return;
        }
        $snapshot = $this->snapshots->decode($attempt['RECEIPT']);
        $this->receipts->insert([
            'PUBLIC_ID' => $this->ids->uuid(),
            'KIND' => ReceiptKindEnum::PAYMENT->value,
            'ATTEMPT_ID' => $attempt['ID'],
            'ORDER_ID' => $attempt['ORDER_ID'],
            'ORDER_PUBLIC_ID' => $attempt['ORDER_PUBLIC_ID'],
            'PROVIDER' => $attempt['PROVIDER'],
            'PROVIDER_PAYMENT_ID' => $providerPaymentId,
            'PROVIDER_KEY' => $this->ids->uuid(),
            'AMOUNT' => $snapshot->total(),
            'ITEMS' => $this->snapshots->encodeItems($snapshot->items),
            'STATUS' => ReceiptStatusEnum::PENDING->value,
            'HANDED_AT' => null,
            'HANDED_BY' => null,
            'NEXT_CHECK_AT' => $this->policy->firstCheckAt($now),
            'CREATED_AT' => $this->policy->utc($now),
        ]);
    }

    /**
     * Закрывающий чек по печатным позициям чека оплаты; создаётся сразу к отправке, отправка ждёт регистрации чека оплаты.
     *
     * @param ReceiptRecord $payment чек оплаты заказа
     *
     * @return bool чек добавлен; false — выдача уже отмечена или печати в чеке нет
     */
    public function printHandedOver(array $payment, int $actorId, \DateTimeImmutable $now): bool
    {
        $items = $this->composer->closing($this->snapshots->decodeItemsJson($payment['ITEMS']));
        if ([] === $items) {
            return false;
        }
        $amount = 0;
        foreach ($items as $item) {
            $amount += $item->total();
        }
        $moment = $this->policy->utc($now);

        return $this->receipts->insert([
            'PUBLIC_ID' => $this->ids->uuid(),
            'KIND' => ReceiptKindEnum::CLOSING->value,
            'ATTEMPT_ID' => $payment['ATTEMPT_ID'],
            'ORDER_ID' => $payment['ORDER_ID'],
            'ORDER_PUBLIC_ID' => $payment['ORDER_PUBLIC_ID'],
            'PROVIDER' => $payment['PROVIDER'],
            'PROVIDER_PAYMENT_ID' => $payment['PROVIDER_PAYMENT_ID'],
            'PROVIDER_KEY' => $this->ids->uuid(),
            'AMOUNT' => $amount,
            'ITEMS' => $this->snapshots->encodeItems($items),
            'STATUS' => ReceiptStatusEnum::QUEUED->value,
            'HANDED_AT' => $moment,
            'HANDED_BY' => $actorId,
            'NEXT_CHECK_AT' => $moment,
            'CREATED_AT' => $moment,
        ]);
    }
}
