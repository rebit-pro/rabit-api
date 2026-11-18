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
        public bool $allowDigitalQuantity = false,
    ) {}
}
