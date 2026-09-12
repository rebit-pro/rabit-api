<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Contract;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;

interface ProductIdGeneratorInterface
{
    public function generate(): ProductId;
}
