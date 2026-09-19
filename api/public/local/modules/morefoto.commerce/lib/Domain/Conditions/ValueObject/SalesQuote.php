<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

final readonly class SalesQuote
{
    /**
     * @param list<PricedSaleItem>  $items
     * @param array<string, string> $gifts          child ID => bundle product ID
     * @param list<string>          $invalidItemIds
     */
    public function __construct(
        public int $catalogRevision,
        public int $conditionsRevision,
        public array $items,
        public array $gifts,
        public array $invalidItemIds,
        public int $subtotal,
        public int $staffDiscount,
        public int $giftSaving,
        public int $total,
    ) {}
}
