<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\Dto;

final readonly class UploadedAvatarInputDto
{
    public function __construct(
        public string $tmpName,
        public int $bytes,
    ) {}
}
