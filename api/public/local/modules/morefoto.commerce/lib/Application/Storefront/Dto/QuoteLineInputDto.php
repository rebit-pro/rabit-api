<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Dto;

final readonly class QuoteLineInputDto
{
    public function __construct(
        public string $assignmentId,
        public string $productId,
        public int $quantity,
    ) {}
}
