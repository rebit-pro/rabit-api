<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access\Dto;

final readonly class InstitutionAssignmentOutputDto
{
    public function __construct(public ?int $curatorId = null, public ?int $headId = null) {}
}
