<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Handoff\Dto;

final readonly class GalleryOpeningOutputDto
{
    public function __construct(
        public string $status,
        public string $openAt,
    ) {}
}
