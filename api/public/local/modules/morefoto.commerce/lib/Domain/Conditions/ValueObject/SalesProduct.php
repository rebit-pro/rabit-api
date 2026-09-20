<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

final readonly class SalesProduct
{
    public ProductId $id;

    public function __construct(
        string $id,
        public ProductKind $kind,
        public int $price,
        public bool $active,
        public bool $staffDiscount,
    ) {
        $this->id = new ProductId($id);
        if (0 > $price || 2147483647 < $price) {
            throw new InvalidConditionsException('Product price is outside the supported range.');
        }
    }
}
