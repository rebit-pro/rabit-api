<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Dto;

use Morefoto\Handoff\Domain\Request\Enum\StaffRequestSortEnum;

final readonly class StaffRequestListInputDto
{
    public function __construct(
        public ?string $institutionId,
        public ?string $shootId,
        public ?string $status,
        public int $page,
        public int $pageSize,
        public StaffRequestSortEnum $sort = StaffRequestSortEnum::UPDATED_AT,
        public bool $descending = true,
    ) {}
}
