<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class ProductSalesOutputDto
{
    public function __construct(public string $id, public string $name, public int $prints) {}
}
