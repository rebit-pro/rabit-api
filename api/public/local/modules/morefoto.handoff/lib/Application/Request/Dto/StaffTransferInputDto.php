<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffTransferInputDto
{
    public function __construct(
        public string $reason,
        public int $revision,
        public string $signature,
    ) {}
}
