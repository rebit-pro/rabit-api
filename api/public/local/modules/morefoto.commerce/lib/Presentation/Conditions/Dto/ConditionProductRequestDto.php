<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Conditions\Dto;

final readonly class ConditionProductRequestDto
{
    public function __construct(
        public string $id,
        public int $price,
        public bool $active,
        public bool $staffDiscount,
    ) {}
}
