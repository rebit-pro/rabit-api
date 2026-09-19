<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

final readonly class StaffPageOutputDto
{
    /** @param list<StaffOutputDto> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public int $total,
    ) {}
}
