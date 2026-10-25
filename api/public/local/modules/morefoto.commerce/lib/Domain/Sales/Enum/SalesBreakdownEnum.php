<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\Enum;

enum SalesBreakdownEnum: string
{
    case INSTITUTION = 'institution';
    case SHOOT = 'shoot';
    case GROUP = 'group';
    case CHILD = 'child';
}
