<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class PhotoRegistration
{
    public function __construct(
        public string $publicId,
        public string $status,
        public int $revision,
        public bool $processingRequired,
        public ?string $existingPhotoId = null,
    ) {}
}
