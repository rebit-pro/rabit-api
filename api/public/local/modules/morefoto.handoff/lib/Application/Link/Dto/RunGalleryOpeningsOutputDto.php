<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class RunGalleryOpeningsOutputDto
{
    public function __construct(
        public int $opened,
        public int $blocked,
        public int $skipped,
    ) {}
}
