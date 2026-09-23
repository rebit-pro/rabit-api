<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Dto;

final readonly class InspectedAvatarDto
{
    public function __construct(
        public string $tmpName,
        public string $mimeType,
        public int $bytes,
        public int $width,
        public int $height,
        public string $fingerprint,
    ) {}
}
