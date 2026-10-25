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
        $body = $html->body($this->groupReport(), ['child-a' => 'child0']);

        self::assertStringContainsString('<h1>Солнышко &amp; Ко</h1>', $body);
        self::assertStringContainsString('Детский сад № 5 · съёмка «Осень»', $body);
        self::assertStringContainsString("1\u{00A0}234,50\u{00A0}₽", $body);
        foreach (['>A<', '>AA<', 'Оплатил', 'Нет заказа', 'Поздняя оплата', 'Заказ без оплаты', 'Печать 10×15', '<img src="var:child0" width="9mm">'] as $text) {
            self::assertStringContainsString($text, $body);
        }
        self::assertSame(1, substr_count($body, '<img src="var:'));
        self::assertStringNotContainsString('data:image/webp', $body);
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

        $pdf = $this->render($report, []);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', substr($pdf, -64));
        self::assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+Manrope/', $pdf);
        self::assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+GolosText/', $pdf);
    }

    public function testAGroupWithRealSizedFramesStaysUnderTheHtmlLimit(): void
    {
        // Thirty children with ~20 KB frames: as base64 inside the HTML they broke mpdf's pcre.backtrack_limit on stage.
        mt_srand(10);
        $children = [];
        $thumbnails = [];
        for ($index = 0; $index < 30; ++$index) {
            $id = 'child-' . $index;
            $children[] = new ChildSalesOutputDto($id, chr(65 + $index % 26) . ($index >= 26 ? 'A' : ''), ChildSalesStatusEnum::PAID, 1, 1, 2, 0, 0, 50_000, 'photo-' . $index);
            $thumbnails[$id] = $this->noise();
        }
        self::assertGreaterThan(100_000, array_sum(array_map('strlen', $thumbnails)));
        $report = new SalesReportOutputDto(
            level: SalesReportLevelEnum::GROUP,
            institution: new SalesReportNodeOutputDto('i', 'Детский сад № 5'),
            shoot: new SalesReportNodeOutputDto('s', 'Осень'),
            group: new SalesReportNodeOutputDto('g', 'Солнышко'),
            totals: new SalesTotalsOutputDto(30, 30, 30, 30, 60, 0, 0, 1_500_000),
            products: [],
            rows: [],
            children: $children,
            generatedAt: new \DateTimeImmutable('2026-10-25T12:00:00+00:00'),
        );

        $pdf = $this->render($report, $thumbnails);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', substr($pdf, -64));
        self::assertSame(30, preg_match_all('/\/Subtype\s*\/Image/', $pdf));
    }

    /** @param array<string, string> $thumbnails */
    private function render(SalesReportOutputDto $report, array $thumbnails): string
    {
        // The bypass-finals file:// wrapper breaks mpdf's seek-based font reads; production has no such wrapper.
        stream_wrapper_restore('file');

        try {
            return (new MpdfSalesReportWriter(new SalesReportHtml(), dirname(__DIR__, 2) . '/resources/pdf/fonts', sys_get_temp_dir() . '/e10-mpdf-' . getmypid()))
                ->pdf($report, $thumbnails)
            ;
        } finally {
            BypassFinals::enable();
        }
    }

    /** A distinct ~20 KB JPEG: random pixels do not compress, so the size is close to a real preview. */
    private function noise(): string
    {
        $image = imagecreatetruecolor(120, 160);
        for ($y = 0; $y < 160; ++$y) {
            for ($x = 0; $x < 120; ++$x) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagejpeg($image, null, 60);

        return (string)ob_get_clean();
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
