<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Dto;

final readonly class GalleryCapabilityOutputDto
{
    public function __construct(
        public string $token,
        public int $revision,
    ) {}
}
