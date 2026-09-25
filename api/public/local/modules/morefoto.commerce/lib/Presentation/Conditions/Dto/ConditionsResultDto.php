<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;
use Rebit\Share\Infrastructure\Controller\Attribute\SkipWhenNull;

final readonly class ConditionsResultDto implements ResultDtoInterface
{
    /**
     * @param list<array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     kind: string,
     *     price: int,
     *     salePrice: int,
     *     printCount: int,
     *     format: string,
     *     unit: string,
     *     staffDiscount: bool,
     *     active: bool,
     * }> $products
     * @param array{
     *     enabled: bool,
     *     rateBps: int,
     *     roundingStep: int,
     *     maxRateBps: int,
     * } $paymentCosts
     */
    public function __construct(
        public int $revision,
        public int $catalogRevision,
        public array $products,
        public int $giftThreshold,
        public bool $giftForStaff,
        public array $paymentCosts,
        #[SkipWhenNull]
        public ?int $conditionsRevision = null,
        #[SkipWhenNull]
        public ?bool $inherit = null,
    ) {}
}
