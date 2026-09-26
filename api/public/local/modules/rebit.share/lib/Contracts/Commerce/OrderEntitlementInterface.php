<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\OrderEntitlementOutputDto;

/** Проекция заказа для выдачи электронных файлов: оплата, срок D10 и купленный состав без цен и покупателя. */
interface OrderEntitlementInterface
{
    /** Заказ по действующему личному ключу; неверный, отозванный и истёкший ключ неотличимы — HttpException ORDER_NOT_FOUND 404. */
    public function byKey(?string $orderKey): OrderEntitlementOutputDto;

    /** Заказ по внутреннему ID для фоновой сборки и выдачи по токену; отсутствие — HttpException ORDER_NOT_FOUND 404. */
    public function byId(int $orderId): OrderEntitlementOutputDto;
}
