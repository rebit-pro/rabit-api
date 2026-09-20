<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\Dto;

final readonly class ConditionsMutationOutputDto
{
    public function __construct(
        public int $revision,
        public int $catalogRevision,
        public int $conditionsRevision,
    ) {}
}
