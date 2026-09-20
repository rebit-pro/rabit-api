<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class MediaGroupOutputDto
{
    public function __construct(
        public int $id,
        public string $publicId,
        public string $name,
        public string $kind,
    ) {}
}
