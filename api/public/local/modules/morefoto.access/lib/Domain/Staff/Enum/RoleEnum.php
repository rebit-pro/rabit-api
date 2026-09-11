<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Enum;

enum RoleEnum: string
{
    case ORGANIZER = 'organizer';
    case CURATOR = 'curator';
    case HEAD = 'head';
    case TEACHER = 'teacher';
}
