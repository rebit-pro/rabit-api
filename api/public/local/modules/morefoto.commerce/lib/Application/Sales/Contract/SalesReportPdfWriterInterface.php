<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Contract;

use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;

interface SalesReportPdfWriterInterface
{
    /** Собирает документ по готовому отчёту. Параметр includeUnordered сохранён для совместимости вызовов. */
    public function pdf(SalesReportOutputDto $report, bool $includeUnordered = true): string;
}
