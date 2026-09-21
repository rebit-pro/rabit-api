<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access\Dto;

final readonly class StaffRequestActorOutputDto
{
    /**
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
        public int $accessRevision,
        public array $institutionIds,
        public array $groupIds,
    ) {}
}
