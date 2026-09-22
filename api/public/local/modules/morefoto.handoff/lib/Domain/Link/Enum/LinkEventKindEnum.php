<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Enum;

enum LinkEventKindEnum: string
{
    case PREPARED = 'prepared';
    case TRANSMITTED = 'transmitted';
    case CORRECTED = 'corrected';
}
