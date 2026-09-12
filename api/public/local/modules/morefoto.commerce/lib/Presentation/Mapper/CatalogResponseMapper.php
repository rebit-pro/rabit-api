<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Mapper;

use Morefoto\Commerce\Application\Catalog\Dto\ListProductsOutputDto;

final readonly class CatalogResponseMapper
{
    /** @return array{
     *     items: list<array{id: string, name: string, description: string, kind: string, price: int, printCount: int, format: string, unit: string, staffDiscount: bool, active: bool}>,
     *     revision: int,
     * } */
    public function data(ListProductsOutputDto $output): array
    {
        $items = [];
        foreach ($output->items as $item) {
            $items[] = [
                'id' => $item->id, 'name' => $item->name, 'description' => $item->description, 'kind' => $item->kind->value,
                'price' => $item->price, 'printCount' => $item->printCount, 'format' => $item->format, 'unit' => $item->unit,
                'staffDiscount' => $item->staffDiscount, 'active' => $item->active,
            ];
        }

        return ['items' => $items, 'revision' => $output->revision];
    }
}
