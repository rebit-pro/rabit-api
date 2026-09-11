<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Catalog\Dto;

use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;

final readonly class ListProductsInputDto
{
    public function __construct(public int $page = 1, public int $pageSize = 20, public ?int $revision = null)
    {
        if (1 > $page || 1000000 < $page || 1 > $pageSize || 100 < $pageSize || (null !== $revision && 1 > $revision)) {
            throw new InvalidProductException('Page must be 1..1000000, pageSize 1..100 and optional revision positive.');
        }
    }
}
