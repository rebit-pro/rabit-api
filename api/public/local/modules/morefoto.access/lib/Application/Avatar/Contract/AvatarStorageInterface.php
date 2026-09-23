<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Contract;

use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;

interface AvatarStorageInterface
{
    public function write(int $userId, int $version, RenderedAvatarDto $avatar): void;

    public function read(int $userId, int $version, AvatarVariantEnum $variant): ?string;

    /** Removes every stored version except the kept one; null removes the employee's avatar files entirely. */
    public function prune(int $userId, ?int $keepVersion): void;
}
