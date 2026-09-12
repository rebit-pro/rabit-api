<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access\Dto;

final readonly class InstitutionScopeOutputDto
{
    /** @param list<int> $institutionIds */
    public function __construct(public string $role, public int $accessRevision, public array $institutionIds) {}
}
