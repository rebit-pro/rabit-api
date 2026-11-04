<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Enum;

enum OpeningStatusEnum: string
{
    case SCHEDULED = 'scheduled';
    case OPENED = 'opened';
    case BLOCKED = 'blocked';
    case CANCELLED = 'cancelled';
}
