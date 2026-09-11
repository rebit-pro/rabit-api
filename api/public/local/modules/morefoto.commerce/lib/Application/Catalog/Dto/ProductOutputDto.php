<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;

final readonly class ProductOutputDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public ProductKind $kind,
        public int $price,
        public int $printCount,
        public string $format,
        public string $unit,
        public bool $staffDiscount,
        public bool $active,
    ) {}

    /** @param array{
     *     UF_UUID: string, UF_NAME: string, UF_DESCRIPTION: string, UF_KIND: string,
     *     UF_PRICE: int|string, UF_PRINT_COUNT: int|string, UF_FORMAT: string, UF_UNIT: string,
     *     UF_STAFF_DISCOUNT: int|string, UF_ACTIVE: int|string,
     * } $row */
    public static function fromRow(array $row): self
    {
        return new self(
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
        );
    }
}
