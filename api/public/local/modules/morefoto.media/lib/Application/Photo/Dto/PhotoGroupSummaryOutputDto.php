<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Dto;

final readonly class PhotoGroupSummaryOutputDto
{
    /** @param list<string> $children */
    public function __construct(
        public int $photos,
        public int $unassigned,
        public array $children,
    ) {}
}
