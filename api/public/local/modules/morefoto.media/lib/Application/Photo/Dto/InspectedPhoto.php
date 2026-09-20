<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class InspectedPhoto
{
    public function __construct(
        public string $tmpName,
        public string $filename,
        public string $mimeType,
        public int $bytes,
        public int $width,
        public int $height,
        public string $fingerprint,
    ) {}
}
