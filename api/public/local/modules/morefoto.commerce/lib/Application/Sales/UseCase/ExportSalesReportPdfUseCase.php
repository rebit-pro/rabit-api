<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Rebit\Share\Application\Contract\File\Dto\PdfFileOutputDto;

/**
 * Выгружает сводку или ведомость продаж в области сотрудника в PDF.
 * Для сводки по группе документ показывает общие итоги и заказы с ФИО.
 */
final readonly class ExportSalesReportPdfUseCase
{
    public function __construct(
        private GetSalesReportUseCase $report,
        private SalesReportPdfWriterInterface $writer,
    ) {}

    public function execute(int $actorId, SalesReportInputDto $input): PdfFileOutputDto
    {
        $report = $this->report->execute($actorId, $input);
        $day = $report->generatedAt->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('Y-m-d');

        return new PdfFileOutputDto(
            filename: 'morefoto-otchet-' . $report->level->value . '-' . $day . '.pdf',
            content: $this->writer->pdf($report, $input->includeUnordered),
        );
    }
}
