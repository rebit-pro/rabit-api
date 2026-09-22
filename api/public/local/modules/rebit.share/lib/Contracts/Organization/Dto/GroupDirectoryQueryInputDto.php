<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupDirectoryQueryInputDto
{
    /**
     * @param null|list<int> $institutionIds native institutions visible to the actor; null means no institution restriction
     * @param null|list<int> $groupIds       native groups visible to the actor; null means no group restriction
     */
    public function __construct(
        public ?array $institutionIds,
        public ?array $groupIds,
        public ?string $institutionId,
        public ?string $shootId,
        public ?string $state,
        public int $page,
        public int $pageSize,
    ) {}
}
