<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SalesReportResultDto implements ResultDtoInterface
{
    /**
     * @param string                        $level       all | institution | shoot | group
     * @param list<ProductSalesResultDto>   $products
     * @param list<SalesReportRowResultDto> $rows
     * @param list<ChildSalesResultDto>     $children
     * @param list<OrderNameSalesResultDto> $orders
     * @param string                        $generatedAt ATOM
     */
    public function __construct(
        public string $level,
        public ?SalesReportNodeResultDto $institution,
        public ?SalesReportNodeResultDto $shoot,
        public ?SalesReportNodeResultDto $group,
        public SalesTotalsResultDto $totals,
        public array $products,
        public array $rows,
        public array $children,
        public string $generatedAt,
        public array $orders = [],
    ) {}
}
