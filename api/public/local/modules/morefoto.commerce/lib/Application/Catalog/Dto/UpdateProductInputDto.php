<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;

final readonly class UpdateProductInputDto
{
    public ProductId $id;

    public function __construct(
        string $id,
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
    ) {
        $this->id = new ProductId($id);
        if (1 > $revision) {
            throw new InvalidProductException('Catalog revision must be positive.');
        }
        if (null === $name && null === $description && null === $kind && null === $price && null === $printCount
            && null === $format && null === $unit && null === $staffDiscount && null === $active) {
            throw new InvalidProductException('At least one product field is required.');
        }
    }

    public function apply(ProductOutputDto $current): ProductDetails
    {
        return new ProductDetails(
            name: $this->name ?? $current->name,
            description: $this->description ?? $current->description,
            kind: $this->kind ?? $current->kind,
            price: $this->price ?? $current->price,
            printCount: $this->printCount ?? $current->printCount,
            format: $this->format ?? $current->format,
            unit: $this->unit ?? $current->unit,
            staffDiscount: $this->staffDiscount ?? $current->staffDiscount,
            active: $this->active ?? $current->active,
        );
    }
}
