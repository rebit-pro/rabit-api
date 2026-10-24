<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class SalesReportInputDto
{
    public function __construct(
        public ?string $institutionId = null,
        public ?string $shootId = null,
        public ?string $groupId = null,
    ) {}
}
