<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File\Dto;

final readonly class ImageContentOutputDto
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $etag,
    ) {}
}
