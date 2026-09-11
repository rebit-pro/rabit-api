<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

final readonly class ListProductsOutputDto
{
    /** @param list<ProductOutputDto> $items */
    public function __construct(
        public array $items,
        public int $revision,
        public int $page,
        public int $pageSize,
        public int $total,
    ) {}
}
