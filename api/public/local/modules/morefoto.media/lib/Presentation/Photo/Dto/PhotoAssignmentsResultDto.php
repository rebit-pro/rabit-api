<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PhotoAssignmentsResultDto implements ResultDtoInterface
{
    /** @param list<string> $photoIds */
    public function __construct(
        public array $photoIds,
        public string $childCode,
        public string $childId,
        public int $revision,
    ) {}
}
