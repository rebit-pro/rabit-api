<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

final readonly class PricedSaleItem
{
    public function __construct(
        public SaleItem $item,
        public SalesProduct $product,
        public int $quantity,
        public int $unitPrice,
        public int $subtotal,
        public int $staffDiscount,
        public int $giftSaving,
        public int $total,
        public bool $coveredByGift,
    ) {}
}
