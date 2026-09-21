<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request\Dto;

final readonly class StaffRequestRowRequestDto
{
    public function __construct(
        public string $id,
        public string $groupId,
        public string $code,
    ) {}
}
