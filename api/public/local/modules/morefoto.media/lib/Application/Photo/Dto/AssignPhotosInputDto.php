<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class AssignPhotosInputDto
{
    /** @param list<string> $photoIds */
    public function __construct(
        public string $shootId,
        public int $revision,
        public array $photoIds,
        public string $childCode,
    ) {}
}
