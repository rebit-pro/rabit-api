<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class WorksheetGroupOutputDto
{
    /**
     * @param list<WorksheetOrderOutputDto> $orders
     * @param list<WorksheetOrderOutputDto> $deferredOrders
     * @param list<WorksheetOrderOutputDto> $outsideOrders
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $institutionName,
        public string $shootName,
        public array $orders,
        public array $deferredOrders,
        public array $outsideOrders,
        public int $total,
        public int $prints,
    ) {}
}
