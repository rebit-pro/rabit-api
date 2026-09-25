<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class UploadPhotoInputDto
{
    public function __construct(
        public string $shootId,
        public string $groupId,
        public string $tmpName,
        public string $filename,
        public int $bytes,
        public ?string $clientFingerprint,
    ) {}
}
