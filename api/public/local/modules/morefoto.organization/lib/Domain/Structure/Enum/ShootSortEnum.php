<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\Enum;

/** Columns the cabinet-wide shoot list is ordered by (UI-DEC-03); the server orders the whole filter, not one page. */
enum ShootSortEnum: string
{
    case DATE = 'date';
    case NAME = 'name';
    case INSTITUTION = 'institution';
    case GROUPS = 'groups';
    case OPEN = 'open';
}
