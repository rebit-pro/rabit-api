<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class GroupMaterialsOutputDto
{
    public function __construct(
        public int $readyPhotos,
        public int $processingPhotos,
        public int $unassignedPhotos,
        public int $children,
        public string $fingerprint,
    ) {}
}
