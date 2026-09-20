<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

final readonly class SaleItem
{
    public ProductId $productId;

    public function __construct(
        public string $id,
        public string $childId,
        public ?string $photoId,
        string $productId,
        public int $quantity,
    ) {
        $this->productId = new ProductId($productId);
        if ('' === trim($id) || '' === trim($childId) || 1 > $quantity || 2147483647 < $quantity) {
            throw new InvalidConditionsException('Sale item ID, child and positive quantity are required.');
        }
    }
}
