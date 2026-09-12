<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\ValueObject;

use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;

final readonly class ProductId
{
    public function __construct(public string $value)
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidProductException('Product ID must be a canonical UUID v4.');
        }
    }
}
