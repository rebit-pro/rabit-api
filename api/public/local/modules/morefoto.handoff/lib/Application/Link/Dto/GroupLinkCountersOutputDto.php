<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Dto;

final readonly class GroupLinkCountersOutputDto
{
    public function __construct(
        public string $referenceNow,
        public int $preparing,
        public int $open,
        public int $closed,
        public int $closingSoon,
        public int $prepared,
    ) {}
}
