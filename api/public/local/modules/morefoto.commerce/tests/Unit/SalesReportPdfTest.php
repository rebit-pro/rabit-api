<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use DG\BypassFinals;
use Morefoto\Commerce\Application\Sales\Dto\ChildSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportNodeOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportRowOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Domain\Sales\Enum\ChildSalesStatusEnum;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\MpdfSalesReportWriter;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\SalesReportHtml;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E10: COM-16 — the branded PDF carries the same numbers as the report and no buyer data.
 *
 * @internal
 */
final class SalesReportPdfTest extends TestCase
{
    public function testTheGroupPageShowsChildrenStatusesAndRoubles(): void
    {
        $html = new SalesReportHtml();
        $body = $html->body($this->groupReport(), ['child-a' => 'webp-bytes']);

        self::assertStringContainsString('<h1>Солнышко &amp; Ко</h1>', $body);
        self::assertStringContainsString('Детский сад № 5 · съёмка «Осень»', $body);
        self::assertStringContainsString("1\u{00A0}234,50\u{00A0}₽", $body);
        foreach (['>A<', '>AA<', 'Оплатил', 'Нет заказа', 'Поздняя оплата', 'Заказ без оплаты', 'Печать 10×15', 'data:image/webp;base64,' . base64_encode('webp-bytes')] as $text) {
            self::assertStringContainsString($text, $body);
        }
        self::assertSame(1, substr_count($body, 'data:image/webp'));
        self::assertStringContainsString('2 / 1', $body);
        self::assertStringContainsString('из 4 детей', $body);
        self::assertStringNotContainsString('@', $body);
        self::assertStringContainsString('24.10.2026 15:00 (МСК)', $html->footer($this->groupReport()));
    }

    public function testAnEmptyLevelSaysThereAreNoSalesYet(): void
    {
        $body = (new SalesReportHtml())->body(new SalesReportOutputDto(
            level: SalesReportLevelEnum::ALL,
            institution: null,
            shoot: null,
            group: null,
            totals: new SalesTotalsOutputDto(0, 0, 0, 0, 0, 0, 0, 0),
            products: [],
            rows: [],
            children: [],
            generatedAt: new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
        ), []);

        self::assertStringContainsString('Все учреждения', $body);
        self::assertStringContainsString('Продаж пока нет', $body);
        self::assertStringNotContainsString('Печать по форматам', $body);
    }

    public function testTheWriterBuildsAPdfWithTheBrandFonts(): void
    {
        $temp = sys_get_temp_dir() . '/e10-mpdf-' . getmypid();
        $report = new SalesReportOutputDto(
            level: SalesReportLevelEnum::INSTITUTION,
            institution: new SalesReportNodeOutputDto('i', 'Детский сад № 5'),
            shoot: null,
            group: null,
            totals: new SalesTotalsOutputDto(30, 12, 9, 10, 40, 6, 1, 1_250_000),
            products: [new ProductSalesOutputDto('p', 'Печать 10×15', 40)],
            rows: [new SalesReportRowOutputDto('s', 'Осень', new SalesTotalsOutputDto(30, 12, 9, 10, 40, 6, 1, 1_250_000))],
            children: [],
            generatedAt: new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
        );

        // The bypass-finals file:// wrapper breaks mpdf's seek-based font reads; production has no such wrapper.
        stream_wrapper_restore('file');

        try {
            $pdf = (new MpdfSalesReportWriter(new SalesReportHtml(), dirname(__DIR__, 2) . '/resources/pdf/fonts', $temp))->pdf($report, []);
        } finally {
            BypassFinals::enable();
        }

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', substr($pdf, -64));
        self::assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+Manrope/', $pdf);
        self::assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+GolosText/', $pdf);
    }

    private function groupReport(): SalesReportOutputDto
    {
        $child = static fn(string $id, string $code, ChildSalesStatusEnum $status, int $orders, int $paid, int $total, ?string $photo): ChildSalesOutputDto => new ChildSalesOutputDto($id, $code, $status, $orders, $paid, 0, 0, 0, $total, $photo);

        return new SalesReportOutputDto(
            level: SalesReportLevelEnum::GROUP,
            institution: new SalesReportNodeOutputDto('i', 'Детский сад № 5'),
            shoot: new SalesReportNodeOutputDto('s', 'Осень'),
            group: new SalesReportNodeOutputDto('g', 'Солнышко & Ко'),
            totals: new SalesTotalsOutputDto(4, 2, 1, 1, 3, 2, 0, 123_450),
            products: [new ProductSalesOutputDto('p', 'Печать 10×15', 3)],
            rows: [],
            children: [
                $child('child-a', 'A', ChildSalesStatusEnum::PAID, 2, 1, 123_450, 'photo-a'),
                $child('child-b', 'B', ChildSalesStatusEnum::NONE, 0, 0, 0, null),
                $child('child-c', 'C', ChildSalesStatusEnum::LATE, 1, 0, 0, null),
                $child('child-aa', 'AA', ChildSalesStatusEnum::UNPAID, 1, 0, 0, 'photo-aa'),
            ],
            generatedAt: new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
        );
    }
}
