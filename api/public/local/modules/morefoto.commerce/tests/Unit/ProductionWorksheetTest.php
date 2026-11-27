<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportAreaOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetPurchaseOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOrderOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetLineOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Application\Sales\Mapper\WorksheetPurchaseMapper;
use Morefoto\Commerce\Application\Sales\Service\ProductionWorksheet;
use Morefoto\Commerce\Application\Sales\Service\WorksheetAssembler;
use Morefoto\Commerce\Application\Sales\Service\WorksheetFiles;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;
use Morefoto\Commerce\Domain\Sales\ValueObject\SalesArea;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\WorksheetHtml;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\MpdfSalesReportWriter;
use Morefoto\Commerce\Infrastructure\Sales\Pdf\SalesReportHtml;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportNodeOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportRequestDto;
use Morefoto\Commerce\Presentation\Sales\SalesReportResultMapper;
use Morefoto\Commerce\Presentation\Sales\WorksheetResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintCompositionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintLineOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionsOutputDto;
use Rebit\Share\Contracts\Production\PrintCompositionInterface;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Rebit\Share\Infrastructure\Helpers\JsonSerializerHelper;
use DG\BypassFinals;
use Bitrix\Main\Text\Converter;
use Morefoto\Commerce\Presentation\Sales\Dto\WorksheetVersionResultDto;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ProductionWorksheetTest extends TestCase
{
    private const string GROUP = '0f8fad5b-d9cb-469f-a165-70867728950e';
    private const string INSTITUTION = '9b2f1c4e-3a5d-4e6f-8a7b-1c2d3e4f5a6b';
    private const string VERSION = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    public function testPreviewUsesNetLineSnapshotsAndKeepsTheOrderNameAndAmountOnce(): void
    {
        $purchases = [
            $this->purchase('l1', 'o1', ['TOTAL' => 180, 'DISCOUNT' => 20, 'ORDER_TOTAL' => 230, 'CHILD' => 'c1', 'CODE' => 'A']),
            $this->purchase('l2', 'o1', ['KIND' => 'digital', 'TOTAL' => 50, 'ORDER_TOTAL' => 230, 'CHILD' => 'c2', 'CODE' => 'B']),
            $this->purchase('l3', 'o2', ['TOTAL' => 70, 'ORDER_TOTAL' => 70]),
            $this->purchase('l4', 'o3', ['PAID' => false, 'STATUS' => 'pending', 'TOTAL' => 90, 'ORDER_TOTAL' => 90]),
            $this->purchase('l5', 'o4', ['PAID' => false, 'STATUS' => 'paid', 'LATE' => true, 'TOTAL' => 80, 'ORDER_TOTAL' => 80]),
        ];
        $report = $this->assemble($purchases);
        self::assertSame(300, $report->total);
        self::assertSame(50, $report->electronicTotal);
        self::assertSame(2, count($report->groups[0]->orders));
        self::assertSame(2, count($report->groups[0]->deferredOrders));
        $order = $report->groups[0]->orders[0];
        self::assertSame(['A', 'B'], $order->childCodes);
        self::assertTrue($order->ambiguous);
        self::assertSame([230, 230, 20], [$order->total, $order->includedTotal, $order->discount]);
        self::assertSame('15x21', $report->columns[0]->format);
        $html = (new WorksheetHtml())->body($report);
        self::assertSame(1, substr_count($html, 'MF-o1'));
        self::assertStringContainsString('Иванов Иван', $html);
        self::assertStringContainsString('Предварительная', $html);
        self::assertStringContainsString('IMG_0007.jpg', $html);
        self::assertStringNotContainsString('Неоплаченные и поздние', $html);
        self::assertStringNotContainsString('MF-o3', $html);
        self::assertStringNotContainsString('MF-o4', $html);
        self::assertStringNotContainsString('15x20', $html);
    }

    public function testFinalUsesOnlyTheChosenLinesAndKeepsSharedFilePrintsSeparateFromEachContribution(): void
    {
        $source = [
            $this->saved('l1', 'o1', 2),
            $this->saved('l4', 'o2', 2),
        ];
        $purchases = [
            $this->purchase('l1', 'o1', ['TOTAL' => 180, 'DISCOUNT' => 20, 'ORDER_TOTAL' => 500, 'PRINT_COUNT' => 2]),
            $this->purchase('l2', 'o1', ['KIND' => 'digital', 'TOTAL' => 50, 'ORDER_TOTAL' => 500]),
            $this->purchase('l3', 'o1', ['TOTAL' => 270, 'DISCOUNT' => 30, 'ORDER_TOTAL' => 500, 'QUANTITY' => 3]),
            $this->purchase('l4', 'o2', ['TOTAL' => 180, 'DISCOUNT' => 20, 'ORDER_TOTAL' => 180, 'PRINT_COUNT' => 2, 'FIRST' => null, 'LAST' => null]),
            $this->purchase('l5', 'o3', ['KIND' => 'digital', 'TOTAL' => 50, 'ORDER_TOTAL' => 50]),
        ];
        $report = $this->assemble($purchases, new PrintCompositionOutputDto($this->version(), $source));
        self::assertSame([360, 100, 4], [$report->total, $report->electronicTotal, $report->prints]);
        self::assertSame(self::VERSION, $report->archive?->id);
        self::assertSame(1, count($report->groups[0]->outsideOrders));
        $orders = $report->groups[0]->orders;
        self::assertSame([180, 180], array_map(static fn(WorksheetOrderOutputDto $order): int => $order->includedTotal, $orders));
        self::assertSame([true, false, false], array_map(static fn(WorksheetLineOutputDto $line): bool => $line->selected, $orders[0]->lines));
        self::assertSame(2, $orders[0]->lines[0]->prints);
        self::assertSame(4, $orders[0]->lines[0]->files[0]->printsInFile);
        self::assertSame('Школа/Группа/Печать/4шт/IMG_0007.jpg', $orders[0]->lines[0]->files[0]->path);
        self::assertNull($orders[1]->firstName);
        $api = (new WorksheetResultMapper())->map($report);
        self::assertSame(360, $api->total);
        self::assertInstanceOf(WorksheetVersionResultDto::class, $api->archive);
        self::assertSame('IMG_0007.jpg', $api->groups[0]->orders[0]->lines[0]->files[0]->filename);
        $json = JsonSerializerHelper::serialize(JsonSerializerHelper::createDefaultNormalizer(), $api);
        $wire = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(360, $wire['total']);
        self::assertSame(self::VERSION, $wire['archive']['id']);
        self::assertArrayNotHasKey('photoId', $wire['groups'][0]['orders'][0]['lines'][0]['files'][0]);
        $html = (new WorksheetHtml())->body($report);
        self::assertStringContainsString(self::VERSION, $html);
        self::assertStringContainsString('Накопительный состав', $html);
        self::assertStringContainsString('В файле: 4', $html);
        self::assertStringContainsString('Не указано', $html);
    }

    public function testMissingOrChangedSourceFailsInsteadOfReturningAnotherComposition(): void
    {
        foreach ([
            [$this->saved('missing', 'o1', 1), 'WORKSHEET_SOURCE_MISSING'],
            [$this->saved('l1', 'o1', 2), 'WORKSHEET_SNAPSHOT_MISMATCH'],
        ] as [$line, $code]) {
            try {
                $this->assemble([$this->purchase('l1', 'o1')], new PrintCompositionOutputDto($this->version(), [$line]));
                self::fail($code);
            } catch (HttpException $error) {
                self::assertSame([$code, 409], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    public function testGiftCoveredLinesAndGiftCodesDoNotBecomeBoughtFilesOrMoney(): void
    {
        $report = $this->assemble([
            $this->purchase('l1', 'o1', ['TOTAL' => 100, 'ORDER_TOTAL' => 100, 'GIFTS' => '["A"]']),
            $this->purchase('l2', 'o1', ['KIND' => 'bundle', 'TOTAL' => 0, 'ORDER_TOTAL' => 100, 'GIFT' => true, 'GIFTS' => '["A"]']),
        ]);
        self::assertSame([100, 0], [$report->total, $report->electronicTotal]);
        self::assertFalse($report->groups[0]->orders[0]->lines[1]->selected);
        self::assertSame(['A'], $report->groups[0]->orders[0]->giftCodes);
    }

    public function testBundleResolutionUsesOnlyThePurchasedChildAndPreservesOriginalNames(): void
    {
        $children = $this->createMock(ChildPhotosInterface::class);
        $children->expects(self::once())->method('ready')->with(2, [101])->willReturn([
            new GalleryAssignmentOutputDto('a', 'p1', 'c1', 101, 'A', 'Z001', 100, 100, 1),
            new GalleryAssignmentOutputDto('b', 'p2', 'c1', 101, 'A', 'Z002', 100, 100, 1),
            new GalleryAssignmentOutputDto('c', 'foreign', 'c2', 102, 'B', 'Z003', 100, 100, 1),
        ]);
        $names = $this->createMock(PhotoFilenamesInterface::class);
        $names->expects(self::once())->method('filenames')->with(['p1', 'p2'])->willReturn(['p1' => '0001.JPG', 'p2' => '0002.JPG']);
        $files = (new WorksheetFiles($children, $names))->resolve([$this->purchase('bought', 'o1', ['KIND' => 'bundle'])]);
        self::assertSame(['p1', 'p2'], array_map(static fn(WorksheetFileOutputDto $file): string => $file->photoId, $files['bought']));
        self::assertSame(['0001.JPG', '0002.JPG'], array_map(static fn(WorksheetFileOutputDto $file): ?string => $file->filename, $files['bought']));
    }

    public function testElectronicBasenameCollisionsKeepFrameCodesInPdfAndChildCodesInApi(): void
    {
        $children = $this->createStub(ChildPhotosInterface::class);
        $children->method('ready')->willReturn([
            new GalleryAssignmentOutputDto('a', 'p1', 'c1', 101, 'A', 'Z001', 100, 100, 1),
            new GalleryAssignmentOutputDto('b', 'p2', 'c1', 101, 'A', 'Z002', 100, 100, 1),
        ]);
        $names = $this->createStub(PhotoFilenamesInterface::class);
        $names->method('filenames')->willReturn(['p1' => 'IMG_0007.JPG', 'p2' => 'IMG_0007.JPG']);
        $purchases = [
            $this->purchase('bundle', 'o1', ['KIND' => 'bundle']),
            $this->purchase('frame-1', 'o2', ['KIND' => 'digital', 'ORDER_TOTAL' => 300]),
            $this->purchase('frame-2', 'o2', ['KIND' => 'digital', 'PHOTO' => 'p2', 'QUANTITY' => 2, 'TOTAL' => 200, 'ORDER_TOTAL' => 300]),
        ];
        $files = (new WorksheetFiles($children, $names))->resolve($purchases);
        $worksheet = (new WorksheetAssembler())->assemble(
            $purchases,
            $files,
            [$this->group()],
            null,
            new PrintVersionsOutputDto(false, []),
            '2026-11-16T10:00:00+00:00',
        );
        $api = (new WorksheetResultMapper())->map($worksheet);
        $public = $api->groups[0]->orders[0]->lines[0]->files;
        self::assertSame(['IMG_0007.JPG', 'IMG_0007.JPG'], array_column($public, 'filename'));
        self::assertSame(['Z001', 'Z002'], array_column($public, 'code'));
        self::assertSame(['A', 'A'], array_column($public, 'childCode'));
        foreach ($public as $file) {
            self::assertArrayNotHasKey('photoId', get_object_vars($file));
            self::assertNull($file->path);
        }
        $html = (new WorksheetHtml())->body($worksheet);
        self::assertStringContainsString('Кадр Z001', $html);
        self::assertStringContainsString('Кадр Z002', $html);
        self::assertStringContainsString(
            '<td>IMG_0007.JPG <span class="meta">(Z001)</span>, IMG_0007.JPG <span class="meta">(Z002)</span>&nbsp;×&nbsp;2</td>',
            $html,
        );
        self::assertSame(1, substr_count($html, 'MF-o2'));
        self::assertStringNotContainsString('Продолжение', $html);
    }

    public function testAuxiliaryPdfSectionsKeepPhysicalMultipliersAndBundleUnits(): void
    {
        $purchases = [
            $this->purchase('l1', 'included'),
            $this->purchase('l2', 'outside', ['QUANTITY' => 2, 'PRINT_COUNT' => 3]),
            $this->purchase('l3', 'deferred', ['QUANTITY' => 2, 'PRINT_COUNT' => 3, 'PAID' => false]),
            $this->purchase('l4', 'unpaid-bundle', ['KIND' => 'bundle', 'QUANTITY' => 2, 'PAID' => false]),
            $this->purchase('l5', 'outside-bundle', ['KIND' => 'bundle', 'QUANTITY' => 2]),
        ];
        $worksheet = $this->assemble($purchases, new PrintCompositionOutputDto($this->version(), [$this->saved('l1', 'included', 1)]));
        $html = (new WorksheetHtml())->body($worksheet);
        self::assertSame(1, substr_count($html, '6 отп. <span class="meta">(2 × 3)</span>'));
        self::assertStringContainsString('2 компл.', $html);
        self::assertStringNotContainsString('MF-deferred', $html);
        self::assertStringNotContainsString('MF-unpaid-bundle', $html);
    }

    public function testLongElectronicOrderKeepsItsNumberOnEveryContinuationRowInBothModes(): void
    {
        $saved = $this->saved('print', '040699', 1);
        $purchases = [$this->purchase($saved->lineId, $saved->orderId)];
        $files = [$saved->lineId => [new WorksheetFileOutputDto($saved->photoId, $saved->filename, $saved->path, $saved->printsInFile)]];
        $frames = 102;
        for ($index = 1; $index <= $frames; ++$index) {
            $code = sprintf('D%03d', $index);
            $purchases[] = $this->purchase($code, '040700', [
                'KIND' => 'digital',
                'ORDER_TOTAL' => $frames * 100,
                'FIRST' => 'Мария',
                'LAST' => 'Орлова',
                'PHOTO' => $code,
            ]);
            $files[$code] = [new WorksheetFileOutputDto($code, $code . '_' . str_repeat('original_', 9) . 'IMG.JPG', null, null)];
        }
        $purchases[] = $this->purchase('next', '040701', ['KIND' => 'digital', 'PHOTO' => 'next']);
        $files['next'] = [new WorksheetFileOutputDto('next', 'NEXT.JPG', null, null)];
        $version = $this->version();
        $versions = new PrintVersionsOutputDto(true, [$version]);
        foreach (['preview' => null, 'final' => new PrintCompositionOutputDto($version, [$saved])] as $mode => $composition) {
            $worksheet = (new WorksheetAssembler())->assemble($purchases, $files, [$this->group()], $composition, $versions, '2026-11-16T10:00:00+00:00');
            $html = (new WorksheetHtml())->body($worksheet);
            $reference = '<td><span class="meta">MF-040700</span></td>';
            self::assertSame(16, substr_count($html, $reference));
            self::assertStringContainsString($reference . '<td></td><td>D007_', $html);
            self::assertStringContainsString($reference . '<td></td><td>D097_', $html);
            self::assertSame(1, substr_count($html, '<strong>Орлова Мария</strong>'));
            self::assertSame(1, substr_count($html, 'MF-040701'));
            self::assertStringNotContainsString('Продолжение', $html);
            $this->pdf($worksheet, 'long-electronic-' . $mode);
        }
    }

    public function testFileReferenceLimitRejects50001InsteadOfTruncatingRepeatedBundles(): void
    {
        $children = $this->createStub(ChildPhotosInterface::class);
        $assignments = [];
        for ($i = 0; $i < 5; ++$i) {
            $assignments[] = new GalleryAssignmentOutputDto('a' . $i, 'p' . $i, 'c1', 101, 'A', 'A' . $i, 100, 100, 1);
        }
        for ($i = 0; $i < 6; ++$i) {
            $assignments[] = new GalleryAssignmentOutputDto('b' . $i, 'q' . $i, 'c2', 102, 'B', 'B' . $i, 100, 100, 1);
        }
        $children->method('ready')->willReturn($assignments);
        $resolver = new WorksheetFiles($children, $this->createStub(PhotoFilenamesInterface::class));
        $purchases = [];
        for ($i = 0; $i < 10000; ++$i) {
            $purchases[] = $this->purchase('bundle-' . $i, 'o' . $i, ['KIND' => 'bundle']);
        }
        $files = $resolver->resolve($purchases);
        self::assertCount(10000, $files);
        self::assertSame(50000, array_sum(array_map(count(...), $files)));
        $values = get_object_vars($purchases[9999]);
        $values['childId'] = 102;
        $values['childPublicId'] = 'c2';
        $purchases[9999] = new WorksheetPurchaseOutputDto(...$values);
        $this->expectExceptionObject(new HttpException('WORKSHEET_TOO_LARGE', 422));
        $resolver->resolve($purchases);
    }

    public function testSavedPrintFilesAlsoConsumeTheTotalFileReferenceBudget(): void
    {
        $purchases = [$this->purchase('print', 'o1')];
        $file = new WorksheetFileOutputDto('p1', 'IMG_0007.JPG', 'print/IMG_0007.JPG', 1);
        $resolver = new WorksheetFiles($this->createStub(ChildPhotosInterface::class), $this->createStub(PhotoFilenamesInterface::class));
        $fixed = ['print' => array_fill(0, 50000, $file)];
        self::assertCount(50000, $resolver->resolve($purchases, $fixed)['print']);
        $fixed['print'][] = $file;
        $this->expectExceptionObject(new HttpException('WORKSHEET_TOO_LARGE', 422));
        $resolver->resolve($purchases, $fixed);
    }

    public function testCommerceLineLimitRejects10001BeforeReturningAnyWorksheet(): void
    {
        $production = $this->createStub(PrintCompositionInterface::class);
        $production->method('available')->willReturn(new PrintVersionsOutputDto(false, []));
        $purchase = $this->purchase('l1', 'o1');
        $row = [
            'ORDER_PUBLIC_ID' => $purchase->orderId, 'NUMBER' => $purchase->number, 'GROUP_PUBLIC_ID' => self::GROUP,
            'GROUP_NAME' => 'Группа', 'INSTITUTION_NAME' => 'Школа', 'SHOOT_NAME' => 'Осень', 'SHOOT_ID' => 2,
            'CHILD_ID' => 101, 'CHILD_PUBLIC_ID' => 'c1', 'CHILD_CODE' => 'A', 'CHILD_FIRST_NAME' => 'Иван', 'CHILD_LAST_NAME' => 'Иванов',
            'PAYMENT_STATUS' => 'paid', 'LATE_PAYMENT' => 0, 'IS_PAID' => 1, 'ORDER_TOTAL' => 100, 'GIFTS' => '[]', 'LINE_ID' => 'l1',
            'PRODUCT_KIND' => 'physical', 'PRODUCT_PUBLIC_ID' => 'print', 'PRODUCT_NAME' => 'Печать', 'PRODUCT_FORMAT' => '15x21',
            'PHOTO_PUBLIC_ID' => 'p1', 'QUANTITY' => 1, 'PRINT_COUNT' => 1, 'UNIT_PRICE' => 100, 'LINE_DISCOUNT' => 0,
            'LINE_TOTAL' => 100, 'COVERED_BY_GIFT' => 0,
        ];
        $result = new class($row) extends Result {
            private int $read = 0;

            /** @param array<string, int|string> $row */
            public function __construct(private array $row) {}

            public function fetch(?Converter $converter = null): array|false
            {
                return 10001 > $this->read++ ? $this->row : false;
            }
        };
        $sales = $this->createMock(SalesReportRepository::class);
        $sales->expects(self::once())->method('worksheet')->with($this->scope()->area, 10001)->willReturn($result);
        $this->expectExceptionObject(new HttpException('WORKSHEET_TOO_LARGE', 422));
        $this->reader($sales, $production)->build(1, new SalesReportInputDto(view: 'worksheet'), $this->scope());
    }

    public function testLargeBundleAndManyColumnsRenderWithoutShrinkingOrRepeatingOrderMoney(): void
    {
        $purchases = [$this->purchase('large-bundle', 'large', ['KIND' => 'bundle', 'TOTAL' => 12345, 'ORDER_TOTAL' => 12345])];
        $files = [];
        for ($i = 0; $i < 300; ++$i) {
            $files['large-bundle'][] = new WorksheetFileOutputDto('p' . $i, sprintf('IMG_%04d.JPG', $i), null, null, 'Z' . $i, 'A');
        }
        $files['large-bundle'][] = new WorksheetFileOutputDto('long', str_repeat('очень_длинное_имя_', 18) . '0007.JPG', null, null, 'LONG', 'A');
        for ($i = 0; $i < 10; ++$i) {
            $values = get_object_vars($this->purchase('wide-' . $i, 'wide-' . $i));
            $values['productId'] = 'product-' . $i;
            $values['productName'] = 'Продукция ' . $i;
            $purchases[] = new WorksheetPurchaseOutputDto(...$values);
            $files['wide-' . $i] = [new WorksheetFileOutputDto('p', 'IMG_0999.JPG', null, null)];
        }
        $worksheet = (new WorksheetAssembler())->assemble(
            $purchases,
            $files,
            [$this->group()],
            null,
            new PrintVersionsOutputDto(false, []),
            '2026-11-16T10:00:00+00:00',
        );
        $html = (new WorksheetHtml())->body($worksheet);
        self::assertSame(1, substr_count($html, '<strong>123,45'));
        self::assertSame(300, preg_match_all('/IMG_0[012]\d{2}\.JPG/', $html));
        self::assertStringContainsString('Файлы · MF-large', $html);
        $this->pdf($worksheet, 'bundle-300-wide');
    }

    public function testFixedArchiveNamesAndPathsDoNotReadMediaAgain(): void
    {
        $children = $this->createMock(ChildPhotosInterface::class);
        $children->expects(self::never())->method('ready');
        $names = $this->createMock(PhotoFilenamesInterface::class);
        $names->expects(self::never())->method('filenames');
        $fixed = ['l1' => [new WorksheetFileOutputDto('p1', '0001.JPG', 'Печать/2шт/0001.JPG', 2)]];
        self::assertSame($fixed, (new WorksheetFiles($children, $names))->resolve([$this->purchase('l1', 'o1')], $fixed));
    }

    public function testTheRealRequestMapperKeepsLegacyDefaultsAndPassesTheExplicitVersion(): void
    {
        $mapper = new SalesReportResultMapper();
        $default = ArrayToDtoMapper::map(StrictRequestValues::normalize([], SalesReportRequestDto::class, false), SalesReportRequestDto::class);
        self::assertSame('summary', $mapper->input($default)->view);
        $values = ['view' => 'worksheet', 'worksheetMode' => 'final', 'archiveType' => 'institution', 'archiveId' => self::VERSION];
        $request = ArrayToDtoMapper::map(StrictRequestValues::normalize($values, SalesReportRequestDto::class, false), SalesReportRequestDto::class);
        $input = $mapper->input($request);
        self::assertSame(['worksheet', 'final', 'institution', self::VERSION], [$input->view, $input->worksheetMode, $input->archiveType, $input->archiveId]);
        $this->expectException(ValidationHttpException::class);
        ArrayToDtoMapper::map(StrictRequestValues::normalize(['view' => 'anything'], SalesReportRequestDto::class, false), SalesReportRequestDto::class);
    }

    public function testDifferentArchiveAreaIsRejectedBeforeReadingPurchases(): void
    {
        $production = $this->createStub(PrintCompositionInterface::class);
        $foreign = new PrintVersionOutputDto('group', self::VERSION, self::INSTITUTION, ['foreign'], 1, 'full', 'ready', 2, '2026-11-12T00:00:00+00:00', '2027-01-01T00:00:00+00:00', 1, 4);
        $production->method('read')->willReturn(new PrintCompositionOutputDto($foreign, []));
        $sales = $this->createMock(SalesReportRepository::class);
        $sales->expects(self::never())->method('worksheet');
        $reader = $this->reader($sales, $production);
        $this->expectExceptionObject(new HttpException('WORKSHEET_AREA_MISMATCH', 422));
        $reader->build(5, new SalesReportInputDto(groupId: self::GROUP, view: 'worksheet', worksheetMode: 'final', archiveType: 'group', archiveId: self::VERSION), $this->scope());
    }

    public function testInvalidFinalSelectionDoesNotFallBackToPreview(): void
    {
        $production = $this->createMock(PrintCompositionInterface::class);
        $production->expects(self::never())->method('read');
        $production->expects(self::never())->method('available');
        $reader = $this->reader($this->createStub(SalesReportRepository::class), $production);
        $this->expectExceptionObject(new HttpException('INVALID_WORKSHEET_SELECTION', 422));
        $reader->build(5, new SalesReportInputDto(view: 'worksheet', worksheetMode: 'final'), $this->scope());
    }

    public function testSnapshotMismatchAndDuplicateLineFail(): void
    {
        foreach ([
            [$this->purchase('l1', 'o1', ['ORDER_TOTAL' => 999])],
            [$this->purchase('l1', 'o1'), $this->purchase('l1', 'o1')],
        ] as $purchases) {
            try {
                $this->assemble($purchases);
                self::fail('Expected snapshot mismatch.');
            } catch (HttpException $error) {
                self::assertSame('WORKSHEET_SNAPSHOT_MISMATCH', $error->getMessage());
            }
        }
    }

    public function testLargeWorksheetProducesLandscapePdfWithBothModesAndAllOrderRows(): void
    {
        $purchases = [];
        $prints = 0;
        for ($index = 0; $index < 200; ++$index) {
            $quantity = 1 + $index % 3;
            $total = 100 * $quantity - (1 < $quantity ? 20 : 0);
            $orderTotal = $total + (0 === $index ? 70 : 0);
            $purchases[] = $this->purchase('bulk-line-' . $index, 'bulk-' . $index, [
                'QUANTITY' => $quantity, 'TOTAL' => $total, 'ORDER_TOTAL' => $orderTotal, 'DISCOUNT' => 1 < $quantity ? 20 : 0,
                'FIRST' => 5 === $index ? null : 'Иван', 'LAST' => 5 === $index ? null : 'Иванов',
            ]);
            $prints += $quantity;
        }
        $purchases[] = $this->purchase('bulk-digital', 'bulk-0', ['KIND' => 'digital', 'TOTAL' => 50, 'ORDER_TOTAL' => 170]);
        $purchases[] = $this->purchase('bulk-bundle', 'bulk-0', ['KIND' => 'bundle', 'TOTAL' => 20, 'ORDER_TOTAL' => 170]);
        $version = new PrintVersionOutputDto(
            'institution',
            self::VERSION,
            self::INSTITUTION,
            [self::GROUP],
            2,
            'full',
            'ready',
            2,
            '2026-11-12T00:00:00+00:00',
            '2027-01-01T00:00:00+00:00',
            1,
            $prints,
        );
        $lines = [];
        foreach ($purchases as $purchase) {
            if ('physical' === $purchase->kind) {
                $lines[] = new PrintLineOutputDto(
                    self::GROUP,
                    'Группа в ZIP',
                    $purchase->lineId,
                    $purchase->orderId,
                    'p1',
                    'product-print',
                    'Печать',
                    '15x21',
                    $purchase->quantity,
                    1,
                    $purchase->quantity,
                    'IMG_0007.jpg',
                    'Школа/Группа/Печать/' . $prints . 'шт/IMG_0007.jpg',
                    $prints,
                );
            }
        }
        $reports = [
            'preview' => $this->assemble($purchases),
            'final' => $this->assemble($purchases, new PrintCompositionOutputDto($version, $lines)),
        ];
        foreach ($reports as $mode => $worksheet) {
            self::assertCount(200, $worksheet->groups[0]->orders);
            $this->pdf($worksheet, $mode);
        }
        self::assertSame(70, $reports['preview']->total - $reports['final']->total);
    }

    private function pdf(WorksheetOutputDto $worksheet, string $name): void
    {
        $report = new SalesReportOutputDto(
            SalesReportLevelEnum::GROUP,
            new SalesReportNodeOutputDto(self::INSTITUTION, 'Школа'),
            null,
            new SalesReportNodeOutputDto(self::GROUP, 'Группа'),
            new SalesTotalsOutputDto(200, 200, 200, 200, $worksheet->prints, 1, 1, $worksheet->total),
            [],
            [],
            [],
            new \DateTimeImmutable('2026-11-16T12:00:00+00:00'),
            worksheet: $worksheet,
        );
        stream_wrapper_restore('file');
        try {
            $pdf = (new MpdfSalesReportWriter(new SalesReportHtml(), dirname(__DIR__, 2) . '/resources/pdf/fonts', sys_get_temp_dir() . '/worksheet-mpdf-' . getmypid()))->pdf($report);
        } finally {
            BypassFinals::enable();
        }
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(2, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
        self::assertSame(1, preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $box));
        self::assertGreaterThan((float)$box[2], (float)$box[1]);
        $evidence = getenv('WORKSHEET_PDF_EVIDENCE_DIR');
        if (false !== $evidence && '' !== $evidence) {
            self::assertSame(strlen($pdf), file_put_contents($evidence . '/worksheet-' . $name . '.pdf', $pdf));
        }
    }

    /** @param array{
     *     TOTAL?: int, ORDER_TOTAL?: int, DISCOUNT?: int, KIND?: string, CHILD?: string, CODE?: string,
     *     PAID?: bool, STATUS?: string, LATE?: bool, FIRST?: null|string, LAST?: null|string,
     *     QUANTITY?: int, PRINT_COUNT?: int, GIFT?: bool, GIFTS?: string, PHOTO?: string,
     * } $values */
    private function purchase(string $line, string $order, array $values = []): WorksheetPurchaseOutputDto
    {
        $v = $values + ['TOTAL' => 100, 'ORDER_TOTAL' => 100, 'DISCOUNT' => 0, 'KIND' => 'physical', 'CHILD' => 'c1', 'CODE' => 'A',
            'PAID' => true, 'STATUS' => 'paid', 'LATE' => false, 'FIRST' => 'Иван', 'LAST' => 'Иванов', 'QUANTITY' => 1, 'PRINT_COUNT' => 1, 'GIFT' => false, 'GIFTS' => '[]', 'PHOTO' => 'p1'];

        return new WorksheetPurchaseOutputDto(
            orderId: $order,
            number: 'MF-' . $order,
            groupId: self::GROUP,
            groupName: 'Группа из снимка',
            institutionName: 'Школа из снимка',
            shootName: 'Осень',
            shootId: 2,
            childId: 101,
            childPublicId: $v['CHILD'],
            childCode: $v['CODE'],
            firstName: $v['FIRST'],
            lastName: $v['LAST'],
            paymentStatus: $v['STATUS'],
            latePayment: $v['LATE'],
            paid: $v['PAID'],
            orderTotal: $v['ORDER_TOTAL'],
            gifts: $v['GIFTS'],
            lineId: $line,
            kind: $v['KIND'],
            productId: 'physical' === $v['KIND'] ? 'product-print' : 'product-' . $v['KIND'],
            productName: 'physical' === $v['KIND'] ? 'Печать' : $v['KIND'],
            format: 'physical' === $v['KIND'] ? '15x21' : '',
            photoId: 'bundle' === $v['KIND'] ? null : $v['PHOTO'],
            quantity: $v['QUANTITY'],
            printCount: $v['PRINT_COUNT'],
            unitPrice: 100,
            discount: $v['DISCOUNT'],
            total: $v['TOTAL'],
            coveredByGift: $v['GIFT'],
        );
    }

    private function version(): PrintVersionOutputDto
    {
        return new PrintVersionOutputDto(
            'institution',
            self::VERSION,
            self::INSTITUTION,
            [self::GROUP],
            2,
            'full',
            'ready',
            2,
            '2026-11-12T00:00:00+00:00',
            '2027-01-01T00:00:00+00:00',
            1,
            4,
        );
    }

    private function saved(string $lineId, string $orderId, int $prints): PrintLineOutputDto
    {
        return new PrintLineOutputDto(
            self::GROUP,
            'Группа в ZIP',
            $lineId,
            $orderId,
            'p1',
            'product-print',
            'Печать',
            '15x21',
            1,
            $prints,
            $prints,
            'IMG_0007.jpg',
            'Школа/Группа/Печать/4шт/IMG_0007.jpg',
            4,
        );
    }

    /**
     * @param list<WorksheetPurchaseOutputDto> $purchases
     */
    private function assemble(array $purchases, ?PrintCompositionOutputDto $composition = null): WorksheetOutputDto
    {
        $files = [];
        foreach ($purchases as $purchase) {
            $files[$purchase->lineId] = [new WorksheetFileOutputDto('p1', 'IMG_0007.jpg', null, null)];
        }
        foreach (null === $composition ? [] : $composition->lines as $line) {
            $files[$line->lineId] = [new WorksheetFileOutputDto($line->photoId, $line->filename, $line->path, $line->printsInFile)];
        }

        return (new WorksheetAssembler())->assemble($purchases, $files, [$this->group()], $composition, new PrintVersionsOutputDto(true, [$this->version()]), '2026-11-12T12:00:00+00:00');
    }

    private function scope(): SalesReportAreaOutputDto
    {
        return new SalesReportAreaOutputDto(SalesReportLevelEnum::GROUP, new SalesArea(null, null, groupId: self::GROUP), [$this->group()]);
    }

    private function group(): GroupDirectoryItemOutputDto
    {
        return new GroupDirectoryItemOutputDto(
            11,
            self::GROUP,
            'Текущее имя группы',
            'children',
            7,
            self::INSTITUTION,
            'Школа',
            2,
            self::VERSION,
            'Осень',
            new GroupCalendarOutputDto('Europe/Moscow', null, null, null, null, 'preparing'),
        );
    }

    private function reader(SalesReportRepository $sales, PrintCompositionInterface $production): ProductionWorksheet
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-11-12T12:00:00+00:00'));

        return new ProductionWorksheet(
            $sales,
            $production,
            new WorksheetPurchaseMapper(),
            new WorksheetFiles($this->createStub(ChildPhotosInterface::class), $this->createStub(PhotoFilenamesInterface::class)),
            new WorksheetAssembler(),
            $clock,
        );
    }
}
