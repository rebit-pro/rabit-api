<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\Service;

use Morefoto\Commerce\Domain\Sales\Enum\ChildSalesStatusEnum;

/**
 * Определяет, что отчёт говорит о ребёнке группы (E10-DEC-06): оплаченный заказ важнее поздней оплаты, поздняя
 * оплата — важнее заказа без оплаты, а ребёнок без единого заказа отмечается отдельно.
 */
final readonly class ChildSalesStatusPolicy
{
    public function status(int $orders, int $paidOrders, int $lateOrders): ChildSalesStatusEnum
    {
        return match (true) {
            0 < $paidOrders => ChildSalesStatusEnum::PAID,
            0 < $lateOrders => ChildSalesStatusEnum::LATE,
            0 < $orders => ChildSalesStatusEnum::UNPAID,
            default => ChildSalesStatusEnum::NONE,
        };
    }
}
