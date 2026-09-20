<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class PhotoOutputDto implements ResponseDtoInterface
{
    public function __construct(
        public string $id,
        public string $status,
        public string $shootId,
        public string $groupId,
        public string $originalGroupId,
        public ?string $childCode,
        public ?string $code,
        public string $filename,
        public int $bytes,
        public int $width,
        public int $height,
        public string $fingerprint,
        public int $revision,
        public ?string $thumbSrc = null,
        public ?string $previewSrc = null,
        public ?string $error = null,
        public ?string $existingPhotoId = null,
    ) {}
}
