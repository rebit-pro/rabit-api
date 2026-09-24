<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Enum;

/** Staff account state shown to the organizer: waiting for the first password, working, or without access. */
enum AccountStatusEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
}
