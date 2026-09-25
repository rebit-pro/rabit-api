<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

final readonly class StaffPageOutputDto
{
    /**
     * @param list<StaffOutputDto> $items
     * @param array<string, int>   $byAccountStatus staff of the filters without the status filter, by account status
     * @param array<string, int>   $byRole          staff of the filters without the role filter, by role
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public int $total,
        public array $byAccountStatus,
        public array $byRole,
    ) {}
}
