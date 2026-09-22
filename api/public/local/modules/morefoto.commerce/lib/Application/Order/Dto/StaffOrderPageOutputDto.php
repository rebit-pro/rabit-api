<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class StaffOrderPageOutputDto
{
    /** @param list<OrderOutputDto> $items */
    public function __construct(public array $items, public int $page, public int $pageSize, public int $total) {}
}
