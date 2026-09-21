<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GalleryGroupOutputDto
{
    public function __construct(
        public int $id,
        public int $shootId,
        public string $publicId,
        public string $institutionName,
        public string $shootName,
        public string $groupName,
        public string $kind,
        public ?string $sentAt,
        public ?string $closesAt,
        public int $revision,
    ) {}
}
