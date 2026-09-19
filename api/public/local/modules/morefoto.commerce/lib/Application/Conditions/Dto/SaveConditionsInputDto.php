<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

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
    ) {
        if (0 > $revision || 1 > $catalogRevision || (null !== $conditionsRevision && 1 > $conditionsRevision)) {
            throw new InvalidConditionsException('Positive condition and catalogue revisions are required.');
        }
        if (0 > $giftThreshold || 2147483647 < $giftThreshold) {
            throw new InvalidConditionsException('Gift threshold is outside the supported range.');
        }
        if ($giftEnabled && 0 === $giftThreshold) {
            throw new InvalidConditionsException('Gift threshold must be positive when the gift is enabled.');
        }
        if (!$giftEnabled && (0 !== $giftThreshold || $giftForStaff)) {
            throw new InvalidConditionsException('Disabled gift must have zero threshold and cannot be enabled for staff.');
        }
        $ids = [];
        foreach ($products as $product) {
            if (isset($ids[$product->id->value])) {
                throw new InvalidConditionsException('Every product must occur exactly once.');
            }
            $ids[$product->id->value] = true;
        }
    }

    public function effectiveGiftThreshold(): int
    {
        return $this->giftEnabled ? $this->giftThreshold : 0;
    }
}
