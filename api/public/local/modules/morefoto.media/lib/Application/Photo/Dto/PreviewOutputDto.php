<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class PreviewOutputDto
{
    public function __construct(
        public string $thumbSrc,
        public string $previewSrc,
    ) {}
}
