<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class GroupSalesOutputDto
{
    /**
     * @param int                         $paidTotal копейки
     * @param list<ProductSalesOutputDto> $products  печать по продукции, больше отпечатков — выше
     */
    public function __construct(
        public string $groupId,
        public int $paidOrders,
        public int $families,
        public int $prints,
        public int $digitalFiles,
        public int $bundles,
        public int $paidTotal,
        public array $products,
    ) {}
}
