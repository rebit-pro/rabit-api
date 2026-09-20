<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class AssignmentMutationOutputDto implements ResponseDtoInterface
{
    /** @param list<string> $photoIds */
    public function __construct(
        public array $photoIds,
        public string $childCode,
        public string $childId,
        public int $revision,
    ) {}
}
