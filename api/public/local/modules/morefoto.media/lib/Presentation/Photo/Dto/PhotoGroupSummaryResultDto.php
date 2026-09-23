<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PhotoGroupSummaryResultDto implements ResultDtoInterface
{
    /** @param list<string> $children */
    public function __construct(
        public int $photos,
        public int $unassigned,
        public array $children,
    ) {}
}
