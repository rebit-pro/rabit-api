<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;

final readonly class GalleryContextOutputDto
{
    public function __construct(
        public GalleryGroupOutputDto $group,
        public string $state,
        public string $referenceNow,
        public int $capabilityRevision,
    ) {}
}
