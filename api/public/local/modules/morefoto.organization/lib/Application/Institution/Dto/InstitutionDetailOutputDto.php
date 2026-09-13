<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Dto;

use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;

final readonly class InstitutionDetailOutputDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public int $revision,
        public ?int $curatorId,
        public ?int $headId,
        public StructurePageOutputDto $shoots,
        public StructurePageOutputDto $groups,
        public ?string $assignmentSignature,
    ) {}
}
