<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\Enum;

enum DeliveryKindEnum: string
{
    /** Письмо после подтверждённой оплаты, одно на заказ. */
    case AUTO = 'auto';
    /** «Отправить ещё раз» со страницы заказа (FIL-05). */
    case MANUAL = 'manual';
}
