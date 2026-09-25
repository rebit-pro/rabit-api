<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupDirectoryPageOutputDto
{
    /** @param list<GroupDirectoryItemOutputDto> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
