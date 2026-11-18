<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

final readonly class ImportGroupsInputDto
{
    /** @param non-empty-list<string> $groups */
    public function __construct(
        public string $institutionId,
        public string $shootId,
        public string $key,
        public string $bearer,
        public array $groups,
    ) {}
}
