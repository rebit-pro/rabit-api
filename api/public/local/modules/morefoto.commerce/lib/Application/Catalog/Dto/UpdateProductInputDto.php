<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductPatch;

final readonly class UpdateProductInputDto
{
    public function __construct(public ProductId $id, public ProductPatch $patch) {}
}
