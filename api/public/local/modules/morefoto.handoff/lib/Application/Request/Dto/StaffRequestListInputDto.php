<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

final readonly class StaffRequestListInputDto
{
    public function __construct(
        public ?string $institutionId,
        public ?string $shootId,
        public ?string $status,
        public int $page,
        public int $pageSize,
    ) {}
}
