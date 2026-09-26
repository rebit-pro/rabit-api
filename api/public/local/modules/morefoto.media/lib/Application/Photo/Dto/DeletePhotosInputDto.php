<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class DeletePhotosInputDto
{
    /** @param non-empty-list<string> $photoIds */
    public function __construct(
        public int $revision,
        public array $photoIds,
    ) {}
}
