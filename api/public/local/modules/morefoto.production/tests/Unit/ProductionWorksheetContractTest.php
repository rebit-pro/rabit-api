<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Worksheet\Service\PrintComposition;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Tests\Unit\Support\InstitutionFixture;
use Morefoto\Production\Tests\Unit\Support\ProductionFixture;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Contracts\Production\Dto\PrintLineOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetPurchaseOutputDto;
use Morefoto\Commerce\Application\Sales\Service\WorksheetAssembler;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;

require_once __DIR__ . '/../bootstrap.php';

/** Сверка публичного read-контракта с настоящими ZIP/CSV на изолированных in-memory поставщиках, без HTTP/E2E.
 * @internal
 */
final class ProductionWorksheetContractTest extends TestCase
{
    public function testSavedCompositionMatchesZipCsvQuantitiesCollisionsAndOriginalBytes(): void
    {
        $f = $this->fixture();
        $shared = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000007';
        $f->base->paidLine(ProductionFixture::GROUP, 'two', 'A002', 'print', '15x21', 2, filename: '0002.JPG');
        $f->base->paidLine(ProductionFixture::GROUP, 'three', 'A003', 'print', '15x21', 3, filename: '0003.JPG');
        $f->base->paidLine(ProductionFixture::GROUP, 'shared-a', 'A007', 'print', '15x21', 1, 2, filename: 'IMG_0007.JPG', photoId: $shared);
        $f->base->paidLine(ProductionFixture::GROUP, 'shared-b', 'B007', 'print', '15x21', 2, 2, filename: 'IMG_0007.JPG', photoId: $shared);
        $f->base->paidLine(ProductionFixture::GROUP, 'collision', 'C007', 'print', '15x21', 6, filename: 'img_0007.jpg');
        $archive = $this->build($f);
        $reader = $this->reader($f);
        $composition = $reader->read(ProductionFixture::CURATOR, 'institution', $archive->publicId);
        self::assertSame($archive->publicId, $composition->version->id);
        self::assertSame($archive->prints, array_sum(array_map(static fn(PrintLineOutputDto $line): int => $line->prints, $composition->lines)));
        $purchases = [];
        $files = [];
        $group = $f->base->groups[ProductionFixture::GROUP];
        $expectedTotal = 0;
        foreach ($f->base->paid[ProductionFixture::GROUP] as $purchase) {
            $net = $purchase->quantity * 12345 - 37;
            $expectedTotal += $net;
            $purchases[] = new WorksheetPurchaseOutputDto(
                orderId: $purchase->orderId,
                number: $purchase->orderNumber,
                groupId: $group->id,
                groupName: $group->name,
                institutionName: $group->institutionName,
                shootName: $group->shootName,
                shootId: $group->shootNativeId,
                childId: 101,
                childPublicId: 'child',
                childCode: $purchase->childCode,
                firstName: 'Иван',
                lastName: 'Иванов',
                paymentStatus: 'paid',
                latePayment: false,
                paid: true,
                orderTotal: $net,
                gifts: '[]',
                lineId: $purchase->lineId,
                kind: 'physical',
                productId: $purchase->productId,
                productName: $purchase->productName,
                format: $purchase->productFormat,
                photoId: $purchase->photoId,
                quantity: $purchase->quantity,
                printCount: $purchase->printCount,
                unitPrice: 12345,
                discount: 37,
                total: $net,
                coveredByGift: false,
            );
        }
        foreach ($composition->lines as $line) {
            $files[$line->lineId] = [new WorksheetFileOutputDto($line->photoId, $line->filename, $line->path, $line->printsInFile)];
        }
        $worksheet = (new WorksheetAssembler())->assemble(
            $purchases,
            $files,
            [$group],
            $composition,
            $reader->available(1, null, InstitutionFixture::INSTITUTION),
            '2026-11-16T10:00:00+00:00',
        );
        self::assertSame($expectedTotal, $worksheet->total);
        self::assertSame($archive->prints, $worksheet->prints);
        $worksheetLines = [];
        foreach ($worksheet->groups[0]->orders as $order) {
            self::assertSame($order->total, $order->includedTotal);
            foreach ($order->lines as $line) {
                $worksheetLines[$line->id] = $line;
            }
        }
        self::assertCount(count($composition->lines), $worksheetLines);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->base->storage()->absolutePath((string)$archive->archivePath)));
        $manifest = $f->export()->execute(2, $archive->publicId)->content;
        self::assertSame($manifest, $zip->getFromName('состав.csv'));
        $csv = [];
        foreach (array_slice(explode("\r\n", trim($manifest)), 1) as $row) {
            $cells = str_getcsv($row, ';', '"', '');
            if (16 === count($cells)) {
                $csv[(string)$cells[11]] = $cells;
            }
        }
        $paths = [];
        foreach ($composition->lines as $line) {
            $paths[$line->lineId] = $line->path;
            self::assertSame($line->filename, basename($line->path));
            self::assertSame($line->path, $csv[$line->lineId][2]);
            $worksheetLine = $worksheetLines[$line->lineId];
            self::assertTrue($worksheetLine->selected);
            self::assertSame($csv[$line->lineId][2], $worksheetLine->files[0]->path);
            self::assertSame($line->filename, $worksheetLine->files[0]->filename);
            self::assertSame((int)$csv[$line->lineId][9], $worksheetLine->prints);
            self::assertSame((int)$csv[$line->lineId][15], $worksheetLine->files[0]->printsInFile);
            self::assertSame($line->prints, (int)$csv[$line->lineId][9]);
            self::assertSame($line->printsInFile, (int)$csv[$line->lineId][15]);
            self::assertSame('JPEG:' . pathinfo($f->base->originalFiles[$line->photoId]->relativePath, PATHINFO_FILENAME), $zip->getFromName($line->path));
        }
        self::assertStringContainsString('/2шт/0002.JPG', $paths['two']);
        self::assertStringContainsString('/3шт/0003.JPG', $paths['three']);
        self::assertSame($paths['shared-a'], $paths['shared-b']);
        self::assertStringContainsString('/6шт/_фото/' . $shared . '/IMG_0007.JPG', $paths['shared-a']);
        self::assertNotSame($paths['shared-a'], $paths['collision']);
        self::assertStringNotContainsString('/1шт/', $paths['first']);
        $zip->close();
    }

    public function testOlderVersionRemainsSelectedAfterSupplementAndNewOpenShoot(): void
    {
        $f = $this->fixture();
        $first = $this->build($f);
        $reader = $this->reader($f);
        $before = $reader->read(1, 'institution', $first->publicId);
        $f->base->paidLine(ProductionFixture::GROUP, 'later-confirmed', 'A002', 'print', '15x21', 2);
        $supplement = $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'worksheet-supplement-01');
        $f->run()->execute(20);
        $later = $reader->read(1, 'institution', $supplement->id);
        self::assertSame('supplement', $later->version->kind);
        self::assertSame(['later-confirmed'], array_map(static fn(PrintLineOutputDto $line): string => $line->lineId, $later->lines));
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-10-17 08:00:00');
        $f->base->names = [];
        $after = $reader->read(1, 'institution', $first->publicId);
        self::assertEquals($before, $after);
        self::assertSame(['first'], array_map(static fn(PrintLineOutputDto $line): string => $line->lineId, $after->lines));
        self::assertCount(3, $reader->available(1, null, InstitutionFixture::INSTITUTION)->versions);
        self::assertSame(['full', 'supplement', 'full'], array_map(static fn(PrintVersionOutputDto $version): string => $version->kind, $reader->available(1, null, InstitutionFixture::INSTITUTION)->versions));
    }

    public function testPreviewAvailabilityDoesNotGrantArchiveAccessToOtherRolesOrInstitutions(): void
    {
        $f = $this->fixture();
        $archive = $this->build($f);
        $reader = $this->reader($f);
        self::assertFalse($reader->available(ProductionFixture::TEACHER, ProductionFixture::GROUP, null)->allowed);
        foreach ([ProductionFixture::TEACHER => 403, ProductionFixture::FOREIGN_CURATOR => 404] as $actor => $status) {
            try {
                $reader->read($actor, 'institution', $archive->publicId);
                self::fail('Foreign archive must not be exposed.');
            } catch (HttpException $error) {
                self::assertSame($status, $error->getCode());
            }
        }
    }

    public function testMissingArchiveFileFailsExplicitlyWithoutRebuilding(): void
    {
        $f = $this->fixture();
        $archive = $this->build($f);
        $reader = $this->reader($f);
        unlink($f->base->storage()->absolutePath((string)$archive->archivePath));
        $this->expectExceptionObject(new HttpException('WORKSHEET_ARCHIVE_NOT_READY', 409));
        $reader->read(1, 'institution', $archive->publicId);
    }

    public function testExpiredVersionFailsExplicitlyEvenIfItsFileStillExists(): void
    {
        $f = $this->fixture();
        $archive = $this->build($f);
        $f->base->at('2027-01-01 00:00:00');
        $this->expectExceptionObject(new HttpException('WORKSHEET_ARCHIVE_NOT_READY', 409));
        $this->reader($f)->read(1, 'institution', $archive->publicId);
    }

    public function testGroupVersionUsesTheSameSavedPathsAndLegacyIsRejected(): void
    {
        $f = $this->fixture();
        $this->build($f);
        $f->base->run();
        $job = array_values($f->base->archives->versions)[0];
        $reader = $this->reader($f);
        $report = $reader->read(2, 'group', $job->publicId);
        self::assertSame($f->base->archives->lines($job->id)[0]->entry, $report->lines[0]->path);
        self::assertSame($job->publicId, $reader->available(2, ProductionFixture::GROUP, null)->versions[0]->id);
        $values = get_object_vars($job);
        $values['layoutVersion'] = 1;
        $f->base->archives->versions[$job->publicId] = new ArchiveVersion(...$values);
        $this->expectExceptionObject(new HttpException('WORKSHEET_LEGACY_LAYOUT', 409));
        $reader->read(2, 'group', $job->publicId);
    }

    public function testProductionRejects10001SavedLinesBeforeReadingTheComposition(): void
    {
        $f = $this->fixture();
        $this->build($f);
        $f->base->run();
        $job = array_values($f->base->archives->versions)[0];
        $values = get_object_vars($job);
        $values['linesCount'] = 10001;
        $f->base->archives->versions[$job->publicId] = new ArchiveVersion(...$values);
        $this->expectExceptionObject(new HttpException('WORKSHEET_TOO_LARGE', 422));
        $this->reader($f)->read(1, 'group', $job->publicId);
    }

    public function testGroupHistoryDoesNotExposeAnArchiveOfThePreviousInstitution(): void
    {
        $f = $this->fixture();
        $this->build($f);
        $f->base->run();
        $job = array_values($f->base->archives->versions)[0];
        $f->base->group(ProductionFixture::GROUP, 1, 8, '2026-10-15 08:00:00');
        $reader = $this->reader($f);
        self::assertSame([], $reader->available(ProductionFixture::FOREIGN_CURATOR, ProductionFixture::GROUP, null)->versions);
        $this->expectExceptionObject(new HttpException('VERSION_NOT_FOUND', 404));
        $reader->read(ProductionFixture::FOREIGN_CURATOR, 'group', $job->publicId);
    }

    private function fixture(): InstitutionFixture
    {
        $f = new InstitutionFixture();
        $f->base->group(ProductionFixture::GROUP, 1, 7, '2026-10-15 08:00:00');
        $f->base->paidLine(ProductionFixture::GROUP, 'first', 'A001', 'print', '15x21', 1, filename: 'IMG_0001.jpg');

        return $f;
    }

    private function build(InstitutionFixture $f): InstitutionArchive
    {
        $result = $f->run()->execute(20);
        self::assertSame(1, $result->built);

        return $f->archives->history(7)[0];
    }

    private function reader(InstitutionFixture $f): PrintComposition
    {
        return new PrintComposition(
            $f->base,
            new ProductionLookup($f->base, $f->base->archives),
            $f->base->archives,
            $f->access,
            $f->archives,
            $f->snapshot,
            $f->base->storage(),
            $f->base,
        );
    }
}
