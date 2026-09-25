<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Avatar\Enum;

/** Square avatar sizes in pixels: 64 for lists and menus, 256 for the profile. */
enum AvatarVariantEnum: string
{
    case THUMB = '64';
    case FULL = '256';

    public function size(): int
    {
        return (int)$this->value;
    }
}
