<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Dto;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;

final readonly class CatalogOutputDto
{
    /** @param list<ProductOutputDto> $products */
    public function __construct(public array $products, public int $giftThreshold, public bool $giftForStaff, public int $revision, public int $conditionsRevision) {}
}
