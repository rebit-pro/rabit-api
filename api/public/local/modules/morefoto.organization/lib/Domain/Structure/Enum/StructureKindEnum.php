<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\Enum;

/** The removable levels of the structure: an institution holds shoots, a shoot holds groups. */
enum StructureKindEnum: string
{
    case INSTITUTION = 'institution';
    case SHOOT = 'shoot';
    case GROUP = 'group';
}
