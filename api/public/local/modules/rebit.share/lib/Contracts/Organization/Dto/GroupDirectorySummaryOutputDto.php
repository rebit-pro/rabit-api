<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class GroupDirectorySummaryOutputDto
{
    public function __construct(
        public int $preparing,
        public int $open,
        public int $closed,
        public int $closingSoon,
        public string $referenceNow,
    ) {}
}
