<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\ValueObject;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;

final readonly class ProductPatch
{
    public function __construct(
        public int $revision,
        public ?string $name = null,
        public ?string $description = null,
        public ?ProductKind $kind = null,
        public ?int $price = null,
        public ?int $printCount = null,
        public ?string $format = null,
        public ?string $unit = null,
        public ?bool $staffDiscount = null,
        public ?bool $active = null,
        public ?bool $allowDigitalQuantity = null,
    ) {
        if (1 > $revision) {
            throw new InvalidProductException('Catalog revision must be positive.');
        }
        if (null === $name && null === $description && null === $kind && null === $price && null === $printCount
            && null === $format && null === $unit && null === $staffDiscount && null === $active && null === $allowDigitalQuantity) {
            throw new InvalidProductException('At least one product field is required.');
        }
    }

    public function allowsDigitalQuantity(ProductKind $currentKind, bool $currentlyAllowed): bool
    {
        $kind = $this->kind ?? $currentKind;

        return $this->allowDigitalQuantity ?? (ProductKind::DIGITAL === $kind && $currentlyAllowed);
    }
}
