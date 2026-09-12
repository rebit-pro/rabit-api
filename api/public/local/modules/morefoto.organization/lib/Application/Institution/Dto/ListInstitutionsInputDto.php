<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

use Morefoto\Organization\Domain\Institution\Exception\InvalidInstitutionException;

final readonly class ListInstitutionsInputDto
{
    public string $query;

    public function __construct(string $query = '', public int $page = 1, public int $pageSize = 50)
    {
        $hasNul = str_contains($query, "\0");
        $query = trim($query);
        if (!mb_check_encoding($query, 'UTF-8') || 100 < mb_strlen($query) || $hasNul
            || 1 > $page || 1000000 < $page || 1 > $pageSize || 100 < $pageSize) {
            throw new InvalidInstitutionException('Invalid search or pagination: query ≤100 characters, page 1–1000000, pageSize 1–100.');
        }
        $this->query = $query;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }
}
