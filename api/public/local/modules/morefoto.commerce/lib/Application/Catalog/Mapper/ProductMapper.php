<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Mapper;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductPatch;

final readonly class ProductMapper
{
    /** @param array{
     *     UF_UUID: string, UF_NAME: string, UF_DESCRIPTION: string, UF_KIND: string,
     *     UF_PRICE: int|string, UF_PRINT_COUNT: int|string, UF_FORMAT: string, UF_UNIT: string,
     *     UF_STAFF_DISCOUNT: int|string, UF_ACTIVE: int|string, UF_ALLOW_DIGITAL_QUANTITY: int|string,
     * } $row */
    public static function fromRow(array $row): ProductOutputDto
    {
        return new ProductOutputDto(
            id: $row['UF_UUID'],
            name: $row['UF_NAME'],
            description: $row['UF_DESCRIPTION'],
            kind: ProductKind::from($row['UF_KIND']),
            price: (int)$row['UF_PRICE'],
            printCount: (int)$row['UF_PRINT_COUNT'],
            format: $row['UF_FORMAT'],
            unit: $row['UF_UNIT'],
            staffDiscount: 1 === (int)$row['UF_STAFF_DISCOUNT'],
            active: 1 === (int)$row['UF_ACTIVE'],
            allowDigitalQuantity: 1 === (int)$row['UF_ALLOW_DIGITAL_QUANTITY'],
        );
    }

    public static function apply(ProductPatch $patch, ProductOutputDto $current): ProductDetails
    {
        return new ProductDetails(
            name: $patch->name ?? $current->name,
            description: $patch->description ?? $current->description,
            kind: $patch->kind ?? $current->kind,
            price: $patch->price ?? $current->price,
            printCount: $patch->printCount ?? $current->printCount,
            format: $patch->format ?? $current->format,
            unit: $patch->unit ?? $current->unit,
            staffDiscount: $patch->staffDiscount ?? $current->staffDiscount,
            active: $patch->active ?? $current->active,
            allowDigitalQuantity: $patch->allowsDigitalQuantity($current->kind, $current->allowDigitalQuantity),
        );
    }
}
