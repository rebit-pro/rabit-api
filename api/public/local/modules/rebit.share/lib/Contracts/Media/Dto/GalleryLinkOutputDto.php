<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class GalleryLinkOutputDto
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $issuedAt,
    ) {}
}
