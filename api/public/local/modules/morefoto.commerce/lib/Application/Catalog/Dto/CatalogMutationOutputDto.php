<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

final readonly class CatalogMutationOutputDto
{
    public function __construct(public string $id, public int $revision) {}
}
