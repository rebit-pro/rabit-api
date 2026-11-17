<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class WorksheetGroupResultDto implements ResultDtoInterface
{
    /**
     * @param list<WorksheetOrderResultDto> $orders
     * @param list<WorksheetOrderResultDto> $deferredOrders
     * @param list<WorksheetOrderResultDto> $outsideOrders
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
