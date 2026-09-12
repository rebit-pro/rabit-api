<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;

final readonly class ProductInputDto
{
    public ProductDetails $details;

    public function __construct(
        string $name,
        string $description,
        ProductKind $kind,
        int $price,
        int $printCount,
        string $format,
        string $unit,
        bool $staffDiscount,
        bool $active,
    ) {
        $this->details = new ProductDetails($name, $description, $kind, $price, $printCount, $format, $unit, $staffDiscount, $active);
    }
}
