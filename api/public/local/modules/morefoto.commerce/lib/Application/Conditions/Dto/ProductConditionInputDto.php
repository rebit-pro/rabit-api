<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

final readonly class ProductConditionInputDto
{
    public function __construct(
        public string $id,
        public int $price,
        public bool $active,
        public bool $staffDiscount,
    ) {}
}
