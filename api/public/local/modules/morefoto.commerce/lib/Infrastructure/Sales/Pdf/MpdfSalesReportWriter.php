<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Sales\Pdf;

use Morefoto\Commerce\Application\Sales\Contract\SalesReportPdfWriterInterface;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Domain\Sales\Exception\SalesReportException;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * Собирает PDF отчёта mpdf: A4, фирменные Manrope и Golos Text из ресурсов модуля, временные файлы вне публичной части.
 * Миниатюры передаются через `imageVars`, а не внутри HTML: тип mpdf определяет по байтам.
 */
final readonly class MpdfSalesReportWriter implements SalesReportPdfWriterInterface
{
    public function __construct(
        private SalesReportHtml $html,
        private string $fontDir,
        private string $tempDir,
    ) {}

    public function pdf(SalesReportOutputDto $report, array $thumbnails): string
    {
        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $this->tempDir,
                'fontDir' => array_merge((new ConfigVariables())->getDefaults()['fontDir'], [$this->fontDir]),
                'fontdata' => (new FontVariables())->getDefaults()['fontdata'] + [
                    'manrope' => ['R' => 'Manrope-Medium.ttf', 'B' => 'Manrope-SemiBold.ttf'],
                    'golos' => ['R' => 'GolosText-Regular.ttf', 'B' => 'GolosText-SemiBold.ttf'],
                ],
                'default_font' => 'golos',
                'margin_left' => 14,
                'margin_right' => 14,
                'margin_top' => 24,
                'margin_bottom' => 16,
                'margin_header' => 8,
                'margin_footer' => 8,
            ]);
            $mpdf->SetTitle('Отчёт о продажах — Море фото');
            $mpdf->SetAuthor('Море фото');
            $mpdf->SetCreator('Море фото');
            $mpdf->SetHTMLHeader($this->html->header());
            $mpdf->SetHTMLFooter($this->html->footer($report));
            $mpdf->WriteHTML($this->html->css(), HTMLParserMode::HEADER_CSS);
            $images = [];
            foreach ($thumbnails as $childId => $thumbnail) {
                $name = 'child' . count($images);
                $mpdf->imageVars[$name] = $thumbnail;
                $images[$childId] = $name;
            }
            $mpdf->WriteHTML($this->html->body($report, $images), HTMLParserMode::HTML_BODY);

            return $mpdf->OutputBinaryData();
        } catch (\Throwable $error) {
            throw new SalesReportException('Cannot render the sales report PDF.', 0, $error);
        }
    }
}
