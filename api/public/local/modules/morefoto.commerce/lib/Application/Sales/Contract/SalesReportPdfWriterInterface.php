<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Contract;

use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;

interface SalesReportPdfWriterInterface
{
    /**
     * Документ PDF фирменного шаблона «Море фото» по готовому отчёту.
     *
     * @param array<string, string> $thumbnails WebP-миниатюры по публичному ID ребёнка
     */
    public function pdf(SalesReportOutputDto $report, array $thumbnails): string;
}
