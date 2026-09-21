<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Storefront\Dto;

final readonly class QuoteLineRequestDto
{
    public function __construct(public string $assignmentId, public string $productId, public int $quantity) {}
}
