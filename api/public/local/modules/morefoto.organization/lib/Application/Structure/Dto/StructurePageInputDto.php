<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

final readonly class StructurePageInputDto
{
    public function __construct(public int $page = 1, public int $pageSize = 50)
    {
        if (1 > $page || 1000000 < $page || 1 > $pageSize || 100 < $pageSize) {
            throw new \InvalidArgumentException('Invalid pagination bounds.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }
}
