<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

final readonly class AssignmentDirectoryOutputDto
{
    /**
     * @param list<AssignmentInstitutionOutputDto> $institutions
     * @param list<AssignmentGroupOutputDto>       $groups
     */
    public function __construct(
        public array $institutions,
        public array $groups,
    ) {}
}
