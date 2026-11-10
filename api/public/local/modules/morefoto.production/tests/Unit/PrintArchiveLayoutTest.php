<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Exception\ArchiveLayoutException;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\NewVersion;
use Morefoto\Production\Tests\Unit\Support\ProductionFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Production\Infrastructure\File\CsvManifestWriter;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * T10-GROUP, T11-T13: правила задания и интеграция настоящего ZIP/CSV с хранилищем версий в памяти. @internal
 *
 * @internal
 */
final class PrintArchiveLayoutTest extends TestCase
{
    public function testQuantitiesUseFoldersAndKeepTheBasename(): void
    {
        $lines = [
            $this->line('1', 'photo-a'),
            $this->line('2', 'photo-b', quantity: 2),
            $this->line('3', 'photo-c', quantity: 3),
            $this->line('4', 'photo-d', quantity: 2, printCount: 3),
        ];
        self::assertSame([
            'Печать/0001.JPEG', 'Печать/2шт/0002.jpg', 'Печать/3шт/Кадр 0003.png', 'Печать/6шт/0004.webp',
        ], array_column(new ArchiveLayout()->arrange($lines, [
            'photo-a' => '0001.JPEG', 'photo-b' => '0002.jpg', 'photo-c' => 'Кадр 0003.png', 'photo-d' => '0004.webp',
        ]), 'entry'));
    }

    public function testLongSafeNamesRemainIntactBeyondTheOldEntryColumnLimit(): void
    {
        $name = str_repeat('0', 246) . '.JPEG';
        $line = $this->line('a', 'photo', name: str_repeat('П', 80));
        $entry = new ArchiveLayout()->arrange([$line], ['photo' => $name])[0]->entry;
        self::assertGreaterThan(255, mb_strlen($entry));
        self::assertLessThanOrEqual(512, mb_strlen($entry));
        self::assertSame($name, basename($entry));
    }

    public function testGeneratedProductFoldersFitTheByteLimitIncludingIdentitySuffixes(): void
    {
        $lines = [$this->line('a', 'a', name: str_repeat('📷', 80)), $this->line('b', 'b', product: 'p2', name: str_repeat('📷', 80))];
        $entries = array_column(new ArchiveLayout()->arrange($lines, ['a' => '0001.JPG', 'b' => '0002.JPG']), 'entry');
        foreach ($entries as $entry) {
            self::assertLessThanOrEqual(255, strlen(dirname($entry)));
            self::assertTrue(mb_check_encoding($entry, 'UTF-8'));
        }
        self::assertNotSame(dirname($entries[0]), dirname($entries[1]));
        self::assertSame('0002.JPG', basename($entries[1]));
    }

    public function testCommonPhotoCountsEveryChildAndEveryPaidLineOfTheProduct(): void
    {
        $lines = [
            $this->line('1', 'common', quantity: 2, code: 'A001'),
            $this->line('2', 'common', quantity: 2, printCount: 3, code: 'B001'),
            $this->line('3', 'common', product: 'magnet', name: 'Магнит', code: 'C001'),
        ];
        $arranged = new ArchiveLayout()->arrange($lines, ['common' => '0007.JPG']);
        self::assertSame(['Печать/8шт/0007.JPG', 'Печать/8шт/0007.JPG', 'Магнит/0007.JPG'], array_column($arranged, 'entry'));
        self::assertSame(['1', '2', '3'], array_column($arranged, 'orderLineId'));
        self::assertSame([2, 6, 1], array_column($arranged, 'prints'));
    }

    public function testCollisionsAreStableAcrossInputOrderAndPreserveCaseAndUnicode(): void
    {
        $lines = [
            $this->line('a', 'photo-a'), $this->line('b', 'photo-b'),
            $this->line('c', 'photo-c', product: 'p2', name: 'Печать'),
            $this->line('d', 'photo-d', product: 'p3', name: 'печать'),
            $this->line('e', 'photo-e'), $this->line('f', 'photo-f'),
        ];
        $names = ['photo-a' => 'IMG_0001.jpg', 'photo-b' => 'img_0001.JPG', 'photo-c' => '0001.jpg', 'photo-d' => '0001.jpg',
            'photo-e' => "\u{00E9}.jpg", 'photo-f' => "e\u{0301}.jpg"];
        $layout = new ArchiveLayout();
        $forward = array_column($layout->arrange($lines, $names), 'entry', 'orderLineId');
        $reverse = array_column($layout->arrange(array_reverse($lines), $names), 'entry', 'orderLineId');
        ksort($reverse);
        self::assertSame($forward, $reverse);
        self::assertCount(6, array_unique($forward));
        self::assertSame('Печать/_фото/photo-a/IMG_0001.jpg', $forward['a']);
        self::assertSame('Печать/_фото/photo-b/img_0001.JPG', $forward['b']);
        self::assertSame($names['photo-e'], basename($forward['e']));
        self::assertSame($names['photo-f'], basename($forward['f']));
        self::assertNotSame(dirname($forward['c']), dirname($forward['d']));
    }

    public function testProductCannotShadowTheManifestInTheRoot(): void
    {
        $entry = new ArchiveLayout()->arrange([$this->line('a', 'photo', name: 'СОСТАВ.CSV')], ['photo' => '0007.JPG'])[0]->entry;
        self::assertSame('СОСТАВ.CSV (p1)/0007.JPG', $entry);
    }

    public function testDistinctAccentedNamesRemainSeparateJobs(): void
    {
        $lines = [$this->line('a', 'photo-a'), $this->line('b', 'photo-b')];
        self::assertSame(['Печать/e.JPG', 'Печать/é.JPG'], array_column(new ArchiveLayout()->arrange($lines, [
            'photo-a' => 'e.JPG', 'photo-b' => 'é.JPG',
        ]), 'entry'));
    }

    public function testFilenameCannotShadowAQuantityOrCollisionDirectory(): void
    {
        $lines = [$this->line('a', 'a'), $this->line('b', 'b', quantity: 2), $this->line('c', 'c')];
        self::assertSame(['Печать/_фото/a/2шт', 'Печать/2шт/normal.jpg', 'Печать/_фото/c/_фото'], array_column(
            new ArchiveLayout()->arrange($lines, ['a' => '2шт', 'b' => 'normal.jpg', 'c' => '_фото']),
            'entry',
        ));
    }

    #[DataProvider('unsafeNames')]
    public function testUnsafeNamesAreRejectedWithoutSilentReplacement(string $name): void
    {
        $this->expectException(ArchiveLayoutException::class);
        $this->expectExceptionMessage('UNSAFE_FILENAME');
        new ArchiveLayout()->arrange([$this->line('a', 'photo')], ['photo' => $name]);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeNames(): iterable
    {
        foreach (['..', '.', '../a.jpg', 'folder/a.jpg', 'folder\a.jpg', "a\0.jpg", "a\n.jpg", 'a:b.jpg', 'con.jpg', 'LPT1.png',
            'COM¹.jpg', 'CONIN$.jpg', 'CON .jpg', "hidden\u{202E}.jpg", 'trailing.jpg.', 'trailing.jpg ', str_repeat('я', 128) . '.jpg', "invalid\xFF.jpg"] as $i => $name) {
            yield 'unsafe-' . $i => [$name];
        }
    }

    public function testManifestPathRemainsLiteralForFormulaLikeProductAndFilename(): void
    {
        $lines = new ArchiveLayout()->arrange([$this->line('a', 'photo', name: '=Печать')], ['photo' => '=0007.JPG']);
        $csv = new CsvManifestWriter()->csv($lines, [], 2);
        $row = $this->csvRows($csv)[1];
        self::assertSame('Продукция =Печать/=0007.JPG', $row[2]);
        self::assertSame($lines[0]->entry, $row[2]);
    }

    public function testUnknownNameDoesNotFallBackToThePhotoCode(): void
    {
        $this->expectExceptionMessage('ORIGINAL_MISSING');
        new ArchiveLayout()->arrange([$this->line('a', 'photo')], []);
    }

    public function testRealZipManifestAndOriginalHashesAgreeIncludingCollisionsAndCommonPhotos(): void
    {
        $f = $this->group();
        $shared = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001';
        $f->paidLine($f::GROUP, 'line-a', 'A001', 'print', 'Печать', 1, filename: '0001.JPEG');
        $f->paidLine($f::GROUP, 'line-b', 'B001', 'print', 'Печать', 2, filename: '0002.JPG');
        $f->paidLine($f::GROUP, 'line-c', 'C001', 'print', 'Печать', 3, filename: '0003.jpg');
        $f->paidLine($f::GROUP, 'line-d', 'D001', 'print', 'Печать', 1, filename: '0001.JPEG');
        $f->paidLine($f::GROUP, 'line-e', 'E001', 'print', 'Печать', 1, filename: 'Общий 007.png', photoId: $shared);
        $f->paidLine($f::GROUP, 'line-f', 'F001', 'print', 'Печать', 2, printCount: 2, photoId: $shared);
        self::assertSame(1, $f->run()->built);
        $version = array_values($f->archives->versions)[0];
        self::assertSame([2, 6, 5, 12], [$version->layoutVersion, $version->linesCount, $version->filesCount, $version->printsCount]);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->storage()->absolutePath($version->archivePath)));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entries[] = (string)$zip->getNameIndex($i);
        }
        self::assertCount(6, $entries);
        self::assertContains('Печать/2шт/0002.JPG', $entries);
        self::assertContains('Печать/3шт/0003.jpg', $entries);
        self::assertContains('Печать/5шт/Общий 007.png', $entries);
        $manifest = (string)$zip->getFromName('состав.csv');
        $rows = $this->csvRows($manifest);
        self::assertCount(7, $rows);
        $ids = [];
        foreach (array_slice($rows, 1) as $row) {
            $ids[] = $row[11];
            self::assertContains($row[2], $entries);
            self::assertSame(hash_file('sha256', $f->originalFiles[$row[13]]->absolutePath), hash('sha256', (string)$zip->getFromName($row[2])));
            self::assertSame('print', $row[14]);
        }
        self::assertSame(['line-a', 'line-b', 'line-c', 'line-d', 'line-e', 'line-f'], $ids);
        $zip->close();
        self::assertSame($manifest, $f->export()->execute($f::CURATOR, $version->publicId)->content);
        $f->originalFiles = [];
        $f->names = [];
        self::assertSame($manifest, $f->export()->execute($f::CURATOR, $version->publicId)->content, 'CSV не зависит от последующего удаления Media.');
    }

    public function testRebuildUsesFrozenNamesAndLegacyVersionsKeepTheirOldPaths(): void
    {
        $f = $this->group();
        $f->paidLine($f::GROUP, 'line-a', 'A001', 'print', 'Печать', 2, filename: '0007.JPEG');
        $f->run();
        $version = array_values($f->archives->versions)[0];
        $before = $f->export()->execute($f::CURATOR, $version->publicId)->content;
        $f->names = array_map(static fn(string $name): string => 'renamed.jpg', $f->names);
        $f->archives->markExpired($version->publicId);
        $f->storage()->delete($version->archivePath);
        $f->renew()->execute($f::ORGANIZER, $version->publicId, str_repeat('a', 32));
        self::assertSame(1, $f->run()->built);
        self::assertSame($before, $f->export()->execute($f::CURATOR, $version->publicId)->content);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->storage()->absolutePath($version->archivePath)));
        self::assertSame('JPEG:A001', $zip->getFromName('Печать/2шт/0007.JPEG'));
        $zip->close();

        $legacy = $this->group();
        $legacy->paidLine($legacy::GROUP, 'line-old', 'A001', 'print', 'Печать', 2);
        $photo = $legacy->paid[$legacy::GROUP][0]->photoId;
        $line = $this->line('line-old', $photo, quantity: 2, entry: 'Печать/A001_x2');
        $id = 'cccccccc-cccc-4ccc-8ccc-000000000001';
        self::assertTrue($legacy->archives->create(new NewVersion(
            $id,
            10,
            $legacy::GROUP,
            7,
            new \DateTimeImmutable('2026-10-15 12:00:00', new \DateTimeZone('UTC')),
            1,
            VersionStatusEnum::PENDING,
            1,
            2,
            0,
            $legacy->storage()->archivePath($legacy::GROUP, $id),
            [$line],
            $legacy->now,
            null,
        )));
        $old = $legacy->archives->find($id);
        self::assertNotNull($old);
        self::assertSame(1, $old->layoutVersion);
        self::assertTrue($legacy->builder()->build($old));
        self::assertTrue($zip->open($legacy->storage()->absolutePath($old->archivePath)));
        self::assertSame('JPEG:A001', $zip->getFromName('Печать/A001_x2.jpg'));
        $csv = (string)$zip->getFromName('состав.csv');
        self::assertCount(11, $this->csvRows($csv)[0]);
        self::assertSame($csv, $legacy->export()->execute($legacy::CURATOR, $id)->content);
        $zip->close();
    }

    #[DataProvider('rejectedGroupNames')]
    public function testRejectedGroupDoesNotBlockOtherArchivesAndRecoversWithoutDuplicateLines(?string $filename, string $errorCode): void
    {
        $f = new ProductionFixture();
        $expiredGroup = '33333333-3333-4333-8333-333333333333';
        $safeGroup = '44444444-4444-4444-8444-444444444444';
        $f->group($expiredGroup, 5, 7, '2026-10-15 10:00:00');
        $f->paidLine($expiredGroup, 'expired', 'E001', 'print', 'Печать', 1);
        $f->at('2026-10-15 11:00:00');
        self::assertSame(1, $f->run()->built);
        $expired = array_values($f->archives->versions)[0];
        self::assertTrue($f->storage()->exists($expired->archivePath));
        $cursor = $f->archives->cursor();

        $f->at('2026-10-30 13:00:00');
        foreach ([$f::GROUP => 10, $f::OTHER_GROUP => 20, $safeGroup => 30] as $group => $id) {
            $f->group($group, $id, 7, '2026-10-30 12:00:00');
        }
        // Первая строка проблемной группы допустима: отклонение второй не должно резервировать часть состава.
        $f->paidLine($f::GROUP, 'bad-valid', 'B001', 'print', 'Печать', 1, filename: '0006.JPG');
        $f->paidLine($f::GROUP, 'bad-name', 'B002', 'print', 'Печать', 2, 3, filename: $filename ?? 'placeholder.jpg');
        $photo = $f->paid[$f::GROUP][1]->photoId;
        if (null === $filename) {
            unset($f->names[$photo]);
        }
        $f->paidLine($f::OTHER_GROUP, 'pending', 'P001', 'print', 'Печать', 1);
        $pending = $f->create()->execute($f::ORGANIZER, $f::OTHER_GROUP, str_repeat('a', 32));
        self::assertSame('pending', $pending->status);
        $f->paidLine($safeGroup, 'safe', 'S001', 'print', 'Печать', 1);
        try {
            $f->create()->execute($f::ORGANIZER, $f::GROUP, str_repeat('b', 32));
            self::fail('Rejected manual snapshot was accepted.');
        } catch (HttpException $error) {
            self::assertSame([$errorCode, 409], [$error->getMessage(), $error->getCode()]);
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning')->with('Print archive snapshot rejected.', [
            'groupId' => $f::GROUP, 'errorCode' => $errorCode,
        ]);

        $first = $f->run(logger: $logger);
        self::assertSame([1, 2, 1, 1], [$first->scheduled, $first->built, $first->failed, $first->expired]);
        self::assertSame(VersionStatusEnum::READY, $f->archives->find($pending->id)?->status);
        self::assertSame(VersionStatusEnum::EXPIRED, $f->archives->find($expired->publicId)?->status);
        self::assertFalse($f->storage()->exists($expired->archivePath));
        self::assertEquals($cursor, $f->archives->cursor());
        self::assertSame([], $f->archives->includedLines(10, new \DateTimeImmutable('2026-10-30 12:00:00', new \DateTimeZone('UTC'))));
        self::assertCount(3, $f->archives->versions, 'У проблемной группы нет частичного снимка/готового ZIP.');
        $hashes = [];
        foreach ($f->archives->byGroups([20, 30]) as $versions) {
            self::assertSame(VersionStatusEnum::READY, $versions[0]->status);
            $hashes[$versions[0]->archivePath] = hash_file('sha256', $f->storage()->absolutePath($versions[0]->archivePath));
        }
        $second = $f->run(logger: $logger);
        self::assertSame([0, 0, 1, 0], [$second->scheduled, $second->built, $second->failed, $second->expired]);
        self::assertEquals($cursor, $f->archives->cursor());
        self::assertCount(3, $f->archives->versions);

        $f->names[$photo] = '0007.JPG';
        $recovered = $f->run();
        self::assertSame([1, 1, 0, 0], [$recovered->scheduled, $recovered->built, $recovered->failed, $recovered->expired]);
        $restored = $f->archives->byGroups([10])[10][0];
        self::assertSame([VersionStatusEnum::READY, 2, 2, 7], [$restored->status, $restored->linesCount, $restored->filesCount, $restored->printsCount]);
        self::assertSame(['bad-valid', 'bad-name'], array_column($f->archives->lines($restored->id), 'orderLineId'));
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->storage()->absolutePath($restored->archivePath)));
        self::assertSame(3, $zip->numFiles);
        self::assertSame('JPEG:B001', $zip->getFromName('Печать/0006.JPG'));
        self::assertSame('JPEG:B002', $zip->getFromName('Печать/6шт/0007.JPG'));
        $zip->close();
        $after = $f->run();
        self::assertSame([0, 0, 0, 0], [$after->scheduled, $after->built, $after->failed, $after->expired]);
        self::assertCount(4, $f->archives->versions);
        $lineIds = [];
        foreach ($f->archives->lines as $lines) {
            foreach ($lines as $line) {
                $lineIds[] = $line->orderLineId;
            }
        }
        sort($lineIds);
        self::assertSame(['bad-name', 'bad-valid', 'expired', 'pending', 'safe'], $lineIds);
        foreach ($hashes as $path => $hash) {
            self::assertSame($hash, hash_file('sha256', $f->storage()->absolutePath($path)), 'Прежний ZIP не пересобран.');
        }
        self::assertSame(30, $f->archives->cursor()->cursorGroupId);
    }

    /** @return iterable<string, array{null|string, string}> */
    public static function rejectedGroupNames(): iterable
    {
        yield 'unsafe' => ['CON.jpg', 'UNSAFE_FILENAME'];
        yield 'empty' => ['', 'ORIGINAL_MISSING'];
        yield 'unknown' => [null, 'ORIGINAL_MISSING'];
    }

    public function testRejectedGroupDoesNotStarveLaterPagesAtLimitOne(): void
    {
        $f = $this->group();
        $f->paidLine($f::GROUP, 'rejected', 'B001', 'print', 'Печать', 1, filename: 'CON.jpg');
        $cursor = $f->archives->cursor();
        $safeGroups = [$f::OTHER_GROUP => 20, '33333333-3333-4333-8333-333333333333' => 30];
        foreach ($safeGroups as $group => $id) {
            $f->group($group, $id, 7, '2026-10-15 12:00:00');
            $f->paidLine($group, 'line-' . $id, 'S' . $id, 'print', 'Печать', 1);
            $run = $f->run(1);
            self::assertSame([1, 1, 1, 0], [$run->scheduled, $run->built, $run->failed, $run->expired]);
            self::assertEquals($cursor, $f->archives->cursor());
            self::assertSame(VersionStatusEnum::READY, $f->archives->byGroups([$id])[$id][0]->status);
        }
        self::assertCount(2, $f->archives->versions);
        $f->names[$f->paid[$f::GROUP][0]->photoId] = '0007.JPG';
        $recovered = $f->run(1);
        self::assertSame([1, 1, 0, 0], [$recovered->scheduled, $recovered->built, $recovered->failed, $recovered->expired]);
        $after = $f->run(1);
        self::assertSame([0, 0, 0, 0], [$after->scheduled, $after->built, $after->failed, $after->expired]);
        self::assertSame(30, $f->archives->cursor()->cursorGroupId);
        self::assertCount(3, $f->archives->versions);
        $lineIds = [];
        foreach ($f->archives->lines as $lines) {
            foreach ($lines as $line) {
                $lineIds[] = $line->orderLineId;
            }
        }
        sort($lineIds);
        self::assertSame(['line-20', 'line-30', 'rejected'], $lineIds);
    }

    public function testLargeArchiveStreamsFilesInsteadOfKeepingTheirBytesInMemory(): void
    {
        $f = $this->group();
        $source = $f->root . '/media/large.bin';
        $stream = fopen($source, 'wb');
        self::assertIsResource($stream);
        self::assertTrue(ftruncate($stream, 8 * 1024 * 1024));
        fclose($stream);
        for ($i = 1; $i <= 20; ++$i) {
            $f->paidLine($f::GROUP, 'line-' . $i, 'A' . $i, 'print', 'Печать', 1, filename: sprintf('%04d.JPG', $i));
            $id = $f->paid[$f::GROUP][$i - 1]->photoId;
            $f->originalFiles[$id] = new OriginalFileOutputDto($id, 'image/jpeg', 8 * 1024 * 1024, 'storage.bin', $source);
        }
        $before = memory_get_usage(true);
        self::assertSame(1, $f->run()->built);
        $version = array_values($f->archives->versions)[0];
        self::assertSame(20, $version->filesCount);
        self::assertGreaterThan(160 * 1024 * 1024, $version->bytes);
        self::assertLessThan(24 * 1024 * 1024, memory_get_usage(true) - $before);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->storage()->absolutePath($version->archivePath)));
        self::assertSame(21, $zip->numFiles);
        $stream = $zip->getStream('Печать/0001.JPG');
        self::assertIsResource($stream);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        self::assertSame(hash_file('sha256', $source), hash_final($hash));
        fclose($stream);
        $zip->close();
    }

    private function group(): ProductionFixture
    {
        $fixture = new ProductionFixture();
        $fixture->group($fixture::GROUP, 10, 7, '2026-10-15 12:00:00');
        $fixture->at('2026-10-15 13:00:00');

        return $fixture;
    }

    private function line(string $id, string $photo, string $product = 'p1', string $name = 'Печать', int $quantity = 1, int $printCount = 1, string $code = 'A001', string $entry = ''): ArchiveLine
    {
        return new ArchiveLine(
            $id,
            'order-' . $id,
            'MF-1',
            '2026-10-10 10:00:00',
            'regular',
            substr($code, 0, 1),
            $photo,
            $code,
            $product,
            $name,
            '10×15',
            $printCount,
            $quantity,
            $quantity * $printCount,
            $entry,
        );
    }

    /** @return list<list<null|string>> */
    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (false !== ($row = fgetcsv($stream, separator: ';', escape: ''))) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
