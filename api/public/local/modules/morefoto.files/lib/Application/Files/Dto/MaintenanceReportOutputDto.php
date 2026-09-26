<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Dto;

final readonly class MaintenanceReportOutputDto
{
    public function __construct(
        public int $processed,
        public int $failed,
    ) {}
}
