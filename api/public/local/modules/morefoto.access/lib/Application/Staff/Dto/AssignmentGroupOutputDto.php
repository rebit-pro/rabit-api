<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

final readonly class AssignmentGroupOutputDto
{
    public function __construct(
        public int $internalId,
        public string $id,
        public string $name,
        public string $shootId,
        public string $shootName,
        public string $institutionId,
        public string $institutionName,
    ) {}
}
