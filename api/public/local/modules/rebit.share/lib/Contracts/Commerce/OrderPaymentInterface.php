<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Commerce\Dto\PayableOrderOutputDto;

/** Платёжная проекция неизменяемого заказа и единственный способ Payments изменить его статус оплаты. */
interface OrderPaymentInterface
{
    /** Заказ по действующему личному ключу; неверный, отозванный и истёкший ключ неотличимы — HttpException ORDER_NOT_FOUND 404. */
    public function byKey(?string $orderKey): PayableOrderOutputDto;

    /** Блокирует заказ в транзакции вызывающего; сам транзакцию не начинает и не завершает. */
    public function lock(int $orderId): PayableOrderOutputDto;

    /** Только после lock(): меняет статус оплаты и версию заказа; оплаченный заказ не возвращается в pending/declined. */
    public function applyPayment(OrderPaymentInputDto $input): void;
}
