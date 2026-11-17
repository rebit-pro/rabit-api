<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Contract;

use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;

interface SalesReportPdfWriterInterface
{
    /** Документ по готовому отчёту; includeUnordered определяет показ строк без заказов, сохраняя общие итоги. */
    public function pdf(SalesReportOutputDto $report, bool $includeUnordered = true): string;
}
