<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Rebit\Share\Application\Contract\File\Dto\PdfFileOutputDto;

/**
 * Выгружает сводку продаж в области сотрудника в компактный PDF с выбранным составом детских строк.
 * Общие итоги сохраняются независимо от показа строк без заказа; изображения для документа не загружаются.
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
