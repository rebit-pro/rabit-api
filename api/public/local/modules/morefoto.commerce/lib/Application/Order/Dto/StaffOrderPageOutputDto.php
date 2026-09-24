<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class StaffOrderPageOutputDto
{
    /**
     * @param list<OrderOutputDto> $items
     * @param array<string, int>   $byProductionStatus every production status of the search without its production filter
     */
    public function __construct(public array $items, public int $page, public int $pageSize, public int $total, public array $byProductionStatus) {}
}
