<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

final readonly class VisibleInstitutionPageOutputDto
{
    /** @param list<VisibleInstitutionOutputDto> $items */
    public function __construct(
        public array $items,
        public string $assignmentSignature,
        public int $page,
        public int $pageSize,
        public int $total,
    ) {}
}
