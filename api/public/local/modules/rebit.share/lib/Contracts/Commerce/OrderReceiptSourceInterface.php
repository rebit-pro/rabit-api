<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\OrderReceiptSourceOutputDto;

/** Состав неизменяемого заказа для кассового чека Payments (G2): email покупателя и строки снимка покупки. */
interface OrderReceiptSourceInterface
{
    /** Заказ по внутреннему ID; отсутствующий — HttpException ORDER_NOT_FOUND 404. */
    public function find(int $orderId): OrderReceiptSourceOutputDto;
}
