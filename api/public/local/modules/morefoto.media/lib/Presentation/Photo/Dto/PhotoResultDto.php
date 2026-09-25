<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PhotoResultDto implements ResultDtoInterface
{
    /** @param list<PhotoAssignmentOutputDto> $assignments */
    public function __construct(
        public string $id,
        public string $status,
        public string $shootId,
        public string $groupId,
        public string $originalGroupId,
        public ?string $childCode,
        public ?string $code,
        public ?int $sequence,
        public array $assignments,
        public string $filename,
        public int $bytes,
        public int $width,
        public int $height,
        public string $fingerprint,
        public int $revision,
        public ?string $thumbSrc,
        public ?string $previewSrc,
        public ?string $error,
        public ?string $existingPhotoId,
    ) {}
}
