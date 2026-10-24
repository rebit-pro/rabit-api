<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Rebit\Share\Application\Contract\File\Dto\PdfFileOutputDto;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;

/**
 * COM-16: выгружает тот же отчёт продаж, что видит сотрудник в кабинете, в PDF фирменного шаблона «Море фото». На уровне
 * группы в документ встраиваются миниатюры детей; недоступная миниатюра не мешает выгрузке.
 */
final readonly class ExportSalesReportPdfUseCase
{
    public function __construct(
        private GetSalesReportUseCase $report,
        private GroupChildrenInterface $media,
        private SalesReportPdfWriterInterface $writer,
    ) {}

    public function execute(int $actorId, SalesReportInputDto $input): PdfFileOutputDto
    {
        $report = $this->report->execute($actorId, $input);
        $thumbnails = [];
        foreach ($report->children as $child) {
            $thumbnail = null === $child->photoId ? null : $this->media->thumbnail($child->photoId);
            if (null !== $thumbnail) {
                $thumbnails[$child->id] = $thumbnail->content;
            }
        }
        $day = $report->generatedAt->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('Y-m-d');

        return new PdfFileOutputDto(
            filename: 'morefoto-otchet-' . $report->level->value . '-' . $day . '.pdf',
            content: $this->writer->pdf($report, $thumbnails),
        );
    }
}
