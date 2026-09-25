<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

final readonly class SaveConditionsInputDto
{
    /** @param list<ProductConditionInputDto> $products */
    public function __construct(
        public int $revision,
        public int $catalogRevision,
        public array $products,
        public bool $giftEnabled,
        public int $giftThreshold,
        public bool $giftForStaff,
        public ?int $conditionsRevision = null,
        public bool $inherit = false,
        public ?PaymentCostsInputDto $paymentCosts = null,
    ) {}
}
