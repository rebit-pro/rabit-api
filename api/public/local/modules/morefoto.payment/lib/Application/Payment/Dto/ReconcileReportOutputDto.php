<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class ReconcileReportOutputDto
{
    public function __construct(
        public int $checked,
        public int $failed,
        public int $receiptsChecked = 0,
        public int $receiptsFailed = 0,
    ) {}
}
