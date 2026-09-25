<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class GroupLinkPageOutputDto
{
    /** @param list<GroupLinkSummaryOutputDto> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public int $total,
        public int $totalPages,
        public GroupLinkCountersOutputDto $summary,
    ) {}
}
