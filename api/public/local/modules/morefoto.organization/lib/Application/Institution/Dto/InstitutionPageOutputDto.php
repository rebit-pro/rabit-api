<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

final readonly class InstitutionPageOutputDto
{
    /** @param list<InstitutionOutputDto> $items */
    public function __construct(public array $items, public int $page, public int $pageSize, public int $total) {}
}
