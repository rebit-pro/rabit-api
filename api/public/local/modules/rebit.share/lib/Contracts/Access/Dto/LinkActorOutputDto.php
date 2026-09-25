<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access\Dto;

final readonly class LinkActorOutputDto
{
    /**
     * @param list<int> $institutionIds assigned institutions of a curator or head
     * @param list<int> $groupIds       assigned groups of a teacher
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
        public array $institutionIds,
        public array $groupIds,
    ) {}
}
