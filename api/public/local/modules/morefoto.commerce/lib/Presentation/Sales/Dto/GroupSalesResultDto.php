<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupSalesResultDto implements ResultDtoInterface
{
    /**
     * @param int                         $paidTotal копейки
     * @param list<ProductSalesResultDto> $products
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
