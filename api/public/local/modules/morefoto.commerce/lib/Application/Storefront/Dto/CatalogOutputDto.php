<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Dto;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;

final readonly class CatalogOutputDto
{
    /**
     * @param list<ProductOutputDto> $products
     * @param list<string>           $receiptChannels
     * @param bool                   $orderEmail      J2: после оплаты придёт письмо со ссылкой на заказ
     */
    public function __construct(
        public array $products,
        public int $giftThreshold,
        public bool $giftForStaff,
        public int $revision,
        public int $conditionsRevision,
        public bool $purchaseEnabled,
        public array $receiptChannels,
        public bool $orderEmail = false,
    ) {}
}
