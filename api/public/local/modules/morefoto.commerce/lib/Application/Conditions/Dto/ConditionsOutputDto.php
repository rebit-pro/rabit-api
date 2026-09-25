<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;

final readonly class ConditionsOutputDto
{
    /**
     * @param list<ProductOutputDto> $products   эффективные базовые цены и доступность
     * @param array<string, int>     $salePrices цена для покупателя по ID товара с учётом политики расходов
     */
    public function __construct(
        public int $revision,
        public int $catalogRevision,
        public int $conditionsRevision,
        public bool $inherit,
        public array $products,
        public int $giftThreshold,
        public bool $giftForStaff,
        public PaymentCostsOutputDto $paymentCosts,
        public array $salePrices,
    ) {}
}
