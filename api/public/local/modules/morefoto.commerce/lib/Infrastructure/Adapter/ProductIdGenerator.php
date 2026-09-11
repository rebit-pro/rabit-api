<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Adapter;

use Morefoto\Commerce\Application\Catalog\Contract\ProductIdGeneratorInterface;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Ramsey\Uuid\Uuid;

final readonly class ProductIdGenerator implements ProductIdGeneratorInterface
{
    public function generate(): ProductId
    {
        return new ProductId(Uuid::uuid4()->toString());
    }
}
