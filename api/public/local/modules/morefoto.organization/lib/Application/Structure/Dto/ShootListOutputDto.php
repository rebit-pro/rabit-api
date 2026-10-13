<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

final readonly class ShootListOutputDto
{
    /** @param list<ShootListItemOutputDto> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public int $total,
        public int $totalPages,
    ) {}
}
