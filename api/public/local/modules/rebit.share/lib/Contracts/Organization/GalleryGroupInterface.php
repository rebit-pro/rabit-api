<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;

interface GalleryGroupInterface
{
    public function get(string $groupId): GalleryGroupOutputDto;
}
