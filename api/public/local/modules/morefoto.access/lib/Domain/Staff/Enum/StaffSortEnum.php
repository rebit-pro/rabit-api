<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Enum;

/** Column the organizer sorts the staff list by. */
enum StaffSortEnum: string
{
    case NAME = 'name';
    case ROLE = 'role';
    case ASSIGNMENTS = 'assignments';
    case STATUS = 'status';
}
