<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class SalesReportRowResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $id,
        public string $name,
        public SalesTotalsResultDto $totals,
    ) {}
}
