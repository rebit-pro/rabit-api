<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Handoff;

use Rebit\Share\Contracts\Handoff\Dto\GalleryOpeningOutputDto;

interface GalleryOpeningInterface
{
    public function current(string $groupId): ?GalleryOpeningOutputDto;
}
