<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Enum;

enum SalesReportLevelEnum: string
{
    case ALL = 'all';
    case INSTITUTION = 'institution';
    case SHOOT = 'shoot';
    case GROUP = 'group';
}
