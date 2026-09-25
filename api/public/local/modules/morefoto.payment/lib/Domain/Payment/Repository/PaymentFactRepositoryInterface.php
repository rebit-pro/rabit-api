<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Repository;

/**
 * Подтверждённые денежные факты: не удаляются и не переписываются.
 *
 * @phpstan-type FactRecord array{
 *     ATTEMPT_ID: int,
 *     ORDER_ID: int,
 *     PROVIDER: string,
 *     PROVIDER_PAYMENT_ID: string,
 *     AMOUNT: int,
 *     INCOME_AMOUNT: null|int,
 *     PAID_AT: string,
 *     LATE_PAYMENT: bool,
 *     CONFIRMED_BY: string,
 *     CREATED_AT: string,
 * }
 */
interface PaymentFactRepositoryInterface
{
    /**
     * @param FactRecord $fact
     *
     * @return bool false — факт этого платежа уже записан
     */
    public function insert(array $fact): bool;

    /** @return list<FactRecord> */
    public function forOrder(int $orderId): array;
}
