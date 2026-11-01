<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;

final readonly class SalesReportOutputDto
{
    /**
     * @param list<ProductSalesOutputDto>   $products печать по продукции, больше отпечатков — выше
     * @param list<SalesReportRowOutputDto> $rows     учреждения, съёмки или группы уровня; у группы пусто
     * @param list<ChildSalesOutputDto>     $children дети группы по коду; у остальных уровней пусто
     * @param list<OrderNameSalesOutputDto> $orders   заказы группы с ФИО на уровне заказа
     */
    public function __construct(
        public SalesReportLevelEnum $level,
        public ?SalesReportNodeOutputDto $institution,
        public ?SalesReportNodeOutputDto $shoot,
        public ?SalesReportNodeOutputDto $group,
        public SalesTotalsOutputDto $totals,
        public array $products,
        public array $rows,
        public array $children,
        public \DateTimeImmutable $generatedAt,
        public array $orders = [],
    ) {}
}
