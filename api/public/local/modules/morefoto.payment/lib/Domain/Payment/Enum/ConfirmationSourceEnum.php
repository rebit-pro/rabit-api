<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Enum;

/** Сигнал, после которого сервер сверил попытку с провайдером; сам сигнал оплату не доказывает. */
enum ConfirmationSourceEnum: string
{
    case START = 'start';
    case RETURN = 'return';
    case NOTIFICATION = 'notification';
    case RECONCILE = 'reconcile';
}
