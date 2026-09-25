<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Enum;

enum DeliveryStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    /** MAX мог принять сообщение: автоматический повтор запрещён. */
    case UNKNOWN = 'unknown';
}
