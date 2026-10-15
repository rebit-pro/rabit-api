<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\Enum;

enum DeliveryStatusEnum: string
{
    /** Ждёт постановки в очередь H1: отправка выключена (J2-DEC-05) или постановка не удалась. */
    case HELD = 'held';
    /** Письмо поставлено в H1; дальше статус показывает операция H1. */
    case QUEUED = 'queued';
    /** Письмо не нужно: срок файлов закончился или оплата больше не подтверждена. */
    case CANCELLED = 'cancelled';
}
