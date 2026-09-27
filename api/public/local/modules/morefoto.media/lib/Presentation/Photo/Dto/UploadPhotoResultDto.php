<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class UploadPhotoResultDto implements ResultDtoInterface
{
    /** @param list<string> $childCodes */
    public function __construct(
        public string $id,
        public string $status,
        public int $revision,
        public ?string $existingPhotoId,
        public array $childCodes,
    ) {}
}
