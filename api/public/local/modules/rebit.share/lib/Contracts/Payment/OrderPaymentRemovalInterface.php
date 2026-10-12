<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Payment;

/** Попытки оплаты удаляемого заказа: деньги и открытая оплата запрещают удаление, отменённые попытки Payments убирает сам. */
interface OrderPaymentRemovalInterface
{
    /**
     * Только в транзакции вызывающего и под его блокировкой заказа: успешная попытка — `409 ORDER_PAID`, открытая
     * (`unknown`/`pending`) — `409 PAYMENT_IN_PROGRESS`, иначе удаляет отменённые попытки заказа.
     */
    public function remove(int $orderId): void;
}
