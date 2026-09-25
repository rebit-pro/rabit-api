<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Enum;

enum AuthorEnum: string
{
    case PARENT = 'parent';
    case STAFF = 'staff';
    case CURATOR = 'curator';
}
