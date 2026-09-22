<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Enum;

enum ProductionStatusEnum: string
{
    case NOT_STARTED = 'not-started';
    case QUEUED = 'queued';
    case PRINTING = 'printing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
}
