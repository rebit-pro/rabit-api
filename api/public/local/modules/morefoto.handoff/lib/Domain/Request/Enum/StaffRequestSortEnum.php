<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Request\Enum;

/** Column the staff list registry is sorted by (#92 DEC-05); the server orders the whole filter, not one page. */
enum StaffRequestSortEnum: string
{
    case UPDATED_AT = 'updatedAt';
    case CREATED_AT = 'createdAt';
    case STATUS = 'status';
    case CREATED_BY_NAME = 'createdByName';
}
