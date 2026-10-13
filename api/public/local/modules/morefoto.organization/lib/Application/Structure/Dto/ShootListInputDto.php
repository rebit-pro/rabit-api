<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Morefoto\Organization\Domain\Structure\Enum\ShootSortEnum;

final readonly class ShootListInputDto
{
    /** @param string $query beginning of the shoot name; empty means every shoot */
    public function __construct(
        public string $query,
        public int $page,
        public int $pageSize,
        public ShootSortEnum $sort,
        public bool $descending,
    ) {}
}
