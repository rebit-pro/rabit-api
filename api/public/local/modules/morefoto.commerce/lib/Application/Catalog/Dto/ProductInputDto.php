<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;

final readonly class ProductInputDto
{
    public function __construct(public ProductDetails $details) {}
}
