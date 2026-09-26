<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class UploadPhotoOutputDto implements ResponseDtoInterface
{
    /** @param list<string> $childCodes */
    public function __construct(
        public string $id,
        public string $status,
        public int $revision,
        public ?string $existingPhotoId = null,
        public array $childCodes = [],
    ) {}
}
