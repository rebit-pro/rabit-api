<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media\Dto;

final readonly class GalleryAssignmentOutputDto
{
    public function __construct(
        public string $assignmentId,
        public string $photoId,
        public string $childId,
        public int $nativeChildId,
        public string $childCode,
        public string $code,
        public int $width,
        public int $height,
        public int $revision,
    ) {}
}
