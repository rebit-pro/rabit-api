<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales;

use Morefoto\Commerce\Application\Sales\Dto\GroupSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Presentation\Sales\Dto\GroupSalesResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\ProductSalesResultDto;

/** Ответ COM-14 без данных покупателей: только числа группы и названия продукции. */
final readonly class SalesResultMapper
{
    public function group(GroupSalesOutputDto $sales): GroupSalesResultDto
    {
        return new GroupSalesResultDto(
            groupId: $sales->groupId,
            paidOrders: $sales->paidOrders,
            families: $sales->families,
            prints: $sales->prints,
            digitalFiles: $sales->digitalFiles,
            bundles: $sales->bundles,
            paidTotal: $sales->paidTotal,
            products: array_map(
                static fn(ProductSalesOutputDto $product): ProductSalesResultDto => new ProductSalesResultDto($product->id, $product->name, $product->prints),
                $sales->products,
            ),
        );
    }
}
