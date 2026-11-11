<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit;

use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionScopeOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Infrastructure\File\ZipArchiveWriter;
use Morefoto\Production\Tests\Unit\Support\InstitutionFixture;
use Morefoto\Production\Tests\Unit\Support\ProductionFixture;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class InstitutionPrintArchiveTest extends TestCase
{
    private function fixture(): InstitutionFixture
    {
        $f = new InstitutionFixture();
        $f->base->group(ProductionFixture::GROUP, 1, 7, '2026-10-15 08:00:00');
        $f->base->paidLine(ProductionFixture::GROUP, 'first-line', 'A001', 'print-10', '10x15', 1, filename: 'IMG_0001.jpg');

        return $f;
    }

    private function first(InstitutionFixture $f): InstitutionArchive
    {
        $result = $f->run()->execute(20);
        self::assertSame(1, $result->scheduled);
        self::assertSame(1, $result->built);
        $archive = $f->archives->history(7)[0];
        self::assertSame(VersionStatusEnum::READY, $archive->status);

        return $archive;
    }

    private function rejected(\Closure $operation, string $code): void
    {
        try {
            $operation();
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
        }
    }

    private function saved(InstitutionFixture $fixture, string $id): InstitutionArchive
    {
        return $fixture->archives->find($id) ?? throw new \LogicException('The archive must be persisted.');
    }

    public function testFailedRequestPersistenceRollsBackDeliveryAndRenewalAndTheSameKeyRecovers(): void
    {
        $f = $this->fixture();
        $first = $this->first($f);
        $ticket = $f->download()->link(1, $first->publicId)->ticket;
        $f->archives->failRemember = true;
        try {
            $f->renew()->execute(1, $first->publicId, 'atomic-renew-key-0001');
            self::fail('The injected INSERT failure must reach the caller.');
        } catch (ProductionStorageException) {
            self::assertSame(1, $f->archives->find($first->publicId)?->generation);
            self::assertNull($f->archives->request(1, hash('sha256', 'atomic-renew-key-0001')));
            self::assertSame($first->bytes, $f->download()->open($ticket)->bytes);
        }
        $f->archives->failRemember = false;
        self::assertSame(2, $f->renew()->execute(1, $first->publicId, 'atomic-renew-key-0001')->generation);
        self::assertSame(2, $f->renew()->execute(1, $first->publicId, 'atomic-renew-key-0001')->generation);

        $f->base->paidLine(ProductionFixture::GROUP, 'late-confirmed-atomic', 'A002', 'print-10', '10x15', 2);
        $f->archives->failRemember = true;
        try {
            $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'atomic-supplement-0001');
            self::fail('The injected INSERT failure must roll back the delivery.');
        } catch (ProductionStorageException) {
            self::assertCount(1, $f->archives->history(7));
            self::assertNull($f->archives->request(1, hash('sha256', 'atomic-supplement-0001')));
            self::assertCount(2, $f->base->archives->includedLines(1, new \DateTimeImmutable($first->groups[0]->deadline, new \DateTimeZone('UTC'))));
        }
        $f->archives->failRemember = false;
        $supplement = $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'atomic-supplement-0001');
        self::assertSame($supplement->id, $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'atomic-supplement-0001')->id);
        self::assertSame(2, $supplement->prints);
        self::assertCount(2, $f->archives->history(7));
        self::assertCount(1, $f->snapshot->lines($f->archives->find($supplement->id) ?? throw new \LogicException()));
    }

    public function testAllHistoricalGroupsWaitForTheLastPrintDeadlineAndTheHour(): void
    {
        $f = $this->fixture();
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-10-15 09:30:00');
        $f->base->paidLine(ProductionFixture::OTHER_GROUP, 'second', 'B001', 'print-10', '10x15', 2);
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        $view = $f->get()->execute(1, InstitutionFixture::INSTITUTION);
        self::assertSame('open', $view->state);
        self::assertCount(1, $view->blockers);
        $f->base->at('2026-10-15 10:00:00');
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        self::assertSame('scheduled', $f->get()->execute(1, InstitutionFixture::INSTITUTION)->state);
        $f->base->at('2026-10-15 10:30:00');
        $archive = $this->first($f);
        self::assertSame(3, $archive->prints);
        self::assertCount(2, $archive->groups);
        // Both groups closed before the L1 cursor; institution delivery intentionally includes existing history.
        self::assertCount(2, $f->base->archives->byGroups([1, 2]));
    }

    public function testNewOpenShootKeepsThePreviousZipAndClosingItAddsToTheFullArchive(): void
    {
        $f = $this->fixture();
        $first = $this->first($f);
        $originalHash = hash_file('sha256', $f->base->storage()->absolutePath((string)$first->archivePath));
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-10-17 08:00:00');
        $f->base->paidLine(ProductionFixture::OTHER_GROUP, 'new-shoot', 'B001', 'print-10', '10x15', 3);
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        $view = $f->get()->execute(2, InstitutionFixture::INSTITUTION);
        self::assertSame('open', $view->state);
        self::assertSame('ready', $view->versions[0]->status);
        $link = $f->download()->link(2, $first->publicId);
        self::assertSame($first->bytes, $f->download()->open($link->ticket)->bytes);
        $this->rejected(fn() => $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'new-shoot-key-0001'), 'PRINT_SALES_OPEN');
        $f->base->at('2026-10-17 09:00:00');
        self::assertSame(1, $f->run()->execute(20)->scheduled);
        $history = $f->archives->history(7);
        self::assertCount(2, $history);
        self::assertSame(4, $history[1]->prints);
        self::assertSame('full', $history[1]->kind);
        self::assertSame($originalHash, hash_file('sha256', $f->base->storage()->absolutePath((string)$first->archivePath)));
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        self::assertCount(2, $f->archives->history(7));
    }

    public function testExtendedPrintDeadlineIsReadAgainAndDigitalDoesNotBlock(): void
    {
        $f = $this->fixture();
        $f->base->group(ProductionFixture::GROUP, 1, 7, '2026-10-15 10:00:00');
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        $f->base->group(ProductionFixture::GROUP, 1, 7, '2026-10-17 10:00:00');
        $f->base->at('2026-10-15 11:00:00');
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        $f->base->at('2026-10-17 11:00:00');
        self::assertSame(1, $this->first($f)->prints);
    }

    public function testZipPreservesBytesQuantityFoldersGroupCollisionsAndExactCsvTrace(): void
    {
        $f = $this->fixture();
        $shared = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000007';
        $f->base->paidLine(ProductionFixture::GROUP, 'shared-child-a', 'A007', 'print-10', '10x15', 1, 2, filename: 'IMG_0007.JPG', photoId: $shared);
        $f->base->paidLine(ProductionFixture::GROUP, 'shared-child-b', 'B007', 'print-10', '10x15', 2, 2, filename: 'IMG_0007.JPG', photoId: $shared);
        $f->base->paidLine(ProductionFixture::GROUP, 'collision', 'C007', 'print-10', '10x15', 6, filename: 'img_0007.jpg');
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-10-15 08:00:00');
        $f->base->paidLine(ProductionFixture::OTHER_GROUP, 'another-group', 'D001', 'print-10', '10x15', 3);
        $archive = $this->first($f);
        self::assertSame(16, $archive->prints);
        $lines = $f->snapshot->lines($archive);
        self::assertCount(5, $lines);
        $paths = array_column($lines, 'entry', 'orderLineId');
        self::assertSame($paths['shared-child-a'], $paths['shared-child-b']);
        self::assertStringContainsString('/6шт/_фото/' . $shared . '/IMG_0007.JPG', $paths['shared-child-a']);
        self::assertStringContainsString('Звёздочки (' . ProductionFixture::OTHER_GROUP . ')/', $paths['another-group']);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->base->storage()->absolutePath((string)$archive->archivePath)));
        $manifest = $f->export()->execute(2, $archive->publicId)->content;
        self::assertSame($manifest, $zip->getFromName('состав.csv'));
        self::assertStringStartsWith("\xEF\xBB\xBF", $manifest);
        foreach ($lines as $line) {
            self::assertStringNotContainsString('_x', $line->entry);
            self::assertStringNotContainsString('/1шт/', $line->entry);
            self::assertStringContainsString('"' . $line->orderLineId . '"', $manifest);
            self::assertStringContainsString('"' . $line->entry . '"', $manifest);
            self::assertSame(hash_file('sha256', $f->base->originalFiles[$line->photoId]->absolutePath), hash('sha256', (string)$zip->getFromName($line->entry)));
        }
        self::assertSame($archive->files + 1, $zip->numFiles);
        $zip->close();
    }

    public function testTimelyLateConfirmationProducesOnlyNewSupplementRowsAndFullReplayIsCumulative(): void
    {
        $f = $this->fixture();
        $first = $this->first($f);
        $f->base->paidLine(ProductionFixture::GROUP, 'timely', 'B001', 'print-10', '10x15', 2);
        $f->base->paidLine(ProductionFixture::GROUP, 'too-late', 'C001', 'print-10', '10x15', 8, paidAt: '2026-10-15 08:00:00');
        $f->base->waiting[ProductionFixture::GROUP] = 4;
        self::assertSame(1, $f->get()->execute(1, InstitutionFixture::INSTITUTION)->outsideLines);
        $supplement = $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'supplement-key-00001');
        self::assertSame(2, $supplement->prints);
        self::assertSame('supplement', $supplement->kind);
        self::assertSame($supplement->id, $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'supplement-key-00001')->id);
        $this->rejected(fn() => $f->supplement()->execute(1, InstitutionFixture::INSTITUTION, 'supplement-key-00002'), 'NOTHING_TO_BUILD');
        $result = $f->run()->execute(20);
        self::assertSame(2, $result->built);
        $history = $f->archives->history(7);
        self::assertSame(3, $history[2]->prints);
        self::assertSame('full', $history[2]->kind);
        self::assertSame(['timely'], array_column($f->snapshot->lines($history[1]), 'orderLineId'));
        self::assertSame(1, $f->archives->find($first->publicId)?->prints);
        self::assertSame(0, $f->get()->execute(1, InstitutionFixture::INSTITUTION)->outsideLines);
        self::assertSame(4, $f->get()->execute(1, InstitutionFixture::INSTITUTION)->waitingOrders);
        $this->rejected(fn() => $f->renew()->execute(1, $first->publicId, 'supplement-key-00001'), 'IDEMPOTENCY_CONFLICT');
    }

    public function testMissingOriginalCannotBecomeReadyAndOrganizerCanRecoverTheSameComposition(): void
    {
        $f = $this->fixture();
        $originals = $f->base->originalFiles;
        $f->base->originalFiles = [];
        self::assertSame(1, $f->run()->execute(20)->failed);
        $failed = $f->archives->history(7)[0];
        self::assertSame(VersionStatusEnum::FAILED, $failed->status);
        self::assertSame('ORIGINAL_MISSING', $failed->error);
        $this->rejected(fn() => $f->download()->link(1, $failed->publicId), 'ARCHIVE_NOT_FOUND');
        $this->rejected(fn() => $f->renew()->execute(2, $failed->publicId, 'recover-key-00001'), 'FORBIDDEN');
        $f->base->originalFiles = $originals;
        $f->renew()->execute(1, $failed->publicId, 'recover-key-00001');
        self::assertSame(1, $f->run()->execute(20)->built);
        self::assertCount(1, $f->archives->history(7));
        self::assertSame($failed->groups, $f->archives->find($failed->publicId)?->groups);
    }

    public function testRightsAreRepeatedWhenTheBrowserGetsBytesAndTicketsExpireAndRevoke(): void
    {
        $f = $this->fixture();
        $archive = $this->first($f);
        self::assertFalse($f->get()->execute(2, InstitutionFixture::INSTITUTION)->canManage);
        $this->rejected(fn() => $f->get()->execute(3, InstitutionFixture::INSTITUTION), 'INSTITUTION_NOT_FOUND');
        $this->rejected(fn() => $f->get()->execute(4, InstitutionFixture::INSTITUTION), 'FORBIDDEN');
        $this->rejected(fn() => $f->download()->link(3, $archive->publicId), 'INSTITUTION_NOT_FOUND');
        $roles = new class($f->base) implements ProductionAccessInterface {
            public bool $revoked = false;

            public function __construct(private ProductionFixture $base) {}

            public function scope(int $actorId): ProductionScopeOutputDto
            {
                return $this->revoked ? new ProductionScopeOutputDto('curator', [], false) : $this->base->scope($actorId);
            }
        };
        $download = new DownloadInstitutionArchiveUseCase(new InstitutionArchiveAccess($roles, $f->institutions, $f->archives), $f->base->links, $f->base->storage(), $f->base);
        $ticket = $download->link(2, $archive->publicId)->ticket;
        self::assertSame($archive->bytes, $download->open($ticket)->bytes);
        $roles->revoked = true;
        $this->rejected(fn() => $download->open($ticket), 'ARCHIVE_NOT_FOUND');
        $roles->revoked = false;
        $this->rejected(fn() => $download->open(substr($ticket, 0, -1) . '!'), 'ARCHIVE_NOT_FOUND');
        $f->base->at('2026-10-15 09:02:00');
        $this->rejected(fn() => $download->open($ticket), 'ARCHIVE_NOT_FOUND');
        $next = $download->link(2, $archive->publicId)->ticket;
        $renewed = $f->renew()->execute(1, $archive->publicId, 'renew-key-000001');
        self::assertSame(2, $renewed->generation);
        self::assertSame(2, $f->renew()->execute(1, $archive->publicId, 'renew-key-000001')->generation);
        $this->rejected(fn() => $download->open($next), 'ARCHIVE_NOT_FOUND');
        self::assertSame($archive->bytes, $download->open($download->link(2, $archive->publicId)->ticket)->bytes);
    }

    public function testExpiryRebuildKeepsFrozenPathsWhileANewShootIsOpen(): void
    {
        $f = $this->fixture();
        $archive = $this->first($f);
        $csv = $f->export()->execute(1, $archive->publicId)->content;
        $f->base->names = array_fill_keys(array_keys($f->base->names), 'renamed.jpg');
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-12-15 08:00:00');
        $f->base->at('2026-10-29 09:00:00');
        self::assertSame(1, $f->run()->execute(20)->expired);
        self::assertFalse($f->base->storage()->exists((string)$archive->archivePath));
        self::assertSame($csv, $f->export()->execute(2, $archive->publicId)->content);
        $f->renew()->execute(1, $archive->publicId, 'rebuild-key-00001');
        self::assertSame(1, $f->run()->execute(20)->built);
        $rebuilt = $f->archives->find($archive->publicId);
        self::assertSame(VersionStatusEnum::READY, $rebuilt?->status);
        self::assertSame($archive->groups, $rebuilt->groups);
        self::assertSame($csv, $f->export()->execute(1, $archive->publicId)->content);
    }

    public function testScopeIsCheckedAgainAfterZipWriteAndNoPartialFileIsPublished(): void
    {
        $f = $this->fixture();
        $archive = $f->snapshot->capture($f->institutions->find(InstitutionFixture::INSTITUTION));
        self::assertNotNull($archive);
        $writer = $this->createStub(ArchiveWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function(array $files, string $csv, string $path) use ($f): int {
            $bytes = new ZipArchiveWriter(new AtomicZipWriter())->write($files, $csv, $path);
            $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-12-15 08:00:00');

            return $bytes;
        });
        self::assertFalse($f->builder($writer)->build($archive));
        self::assertSame('COMPOSITION_CHANGED', $f->archives->find($archive->publicId)?->error);
        self::assertSame([], glob($f->base->root . '/files/production/*/*.zip'));
    }

    public function testRenewalCannotSkipTheFullScopeBeforeTheFirstSuccessfulPublication(): void
    {
        $f = $this->fixture();
        $archive = $f->snapshot->capture($f->institutions->find(InstitutionFixture::INSTITUTION));
        self::assertNotNull($archive);
        $f->base->group(ProductionFixture::OTHER_GROUP, 2, 7, '2026-12-15 08:00:00');
        $f->base->paidLine(ProductionFixture::OTHER_GROUP, 'new-shoot', 'B001', 'print-10', '10x15', 2);
        for ($generation = 1; $generation <= 3; ++$generation) {
            $current = $f->archives->find($archive->publicId);
            self::assertNotNull($current);
            self::assertFalse($f->builder()->build($current));
            $failed = $f->archives->find($archive->publicId);
            self::assertSame(VersionStatusEnum::FAILED, $failed?->status);
            self::assertSame('COMPOSITION_CHANGED', $failed->error);
            self::assertNull($failed->firstReadyAt);
            $this->rejected(fn() => $f->download()->link(1, $archive->publicId), 'ARCHIVE_NOT_FOUND');
            if (3 > $generation) {
                $f->renew()->execute(1, $archive->publicId, 'review-scope-retry-000' . $generation);
            }
        }
        self::assertSame([], glob($f->base->root . '/files/production/*/*.zip'));
        $f->base->at('2026-12-15 09:00:00');
        self::assertSame(1, $f->run()->execute(20)->built);
        $full = $f->archives->history(7)[1];
        self::assertCount(2, $full->groups);
        self::assertSame(3, $full->prints);
        self::assertNotNull($full->firstReadyAt);
    }

    public function testInterruptedExhaustedLeaseBecomesFailedAndCanBeRenewed(): void
    {
        $f = $this->fixture();
        $archive = $f->snapshot->capture($f->institutions->find(InstitutionFixture::INSTITUTION));
        self::assertNotNull($archive);
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $owner = sprintf('eeeeeeee-eeee-4eee-8eee-%012d', $attempt);
            $path = $f->base->storage()->archivePath(InstitutionFixture::INSTITUTION, $owner);
            self::assertTrue($f->archives->claim($archive->publicId, $owner, $path, $f->base->now, $f->base->now->modify('+30 minutes')));
            self::assertFalse($f->archives->claim($archive->publicId, 'other-owner', $path, $f->base->now, $f->base->now->modify('+30 minutes')));
            $f->base->at($f->base->now->modify('+31 minutes')->format('Y-m-d H:i:s'));
        }
        self::assertSame(1, $f->run()->execute(20)->failed);
        $failed = $f->archives->find($archive->publicId);
        self::assertSame(VersionStatusEnum::FAILED, $failed?->status);
        self::assertSame(4, $failed->attempts);
        self::assertSame('ARCHIVE_FAILED', $failed->error);
        self::assertSame([], $f->archives->due($f->base->now, 20));
        $f->renew()->execute(1, $archive->publicId, 'review-lease-renew-0001');
        self::assertSame(1, $f->run()->execute(20)->built);
        self::assertCount(1, $f->archives->history(7));
    }

    public function testRenewalBeforeExpiryCleanupRetainsTheOldFileUntilSuccessfulDeletion(): void
    {
        $f = $this->fixture();
        $archive = $this->first($f);
        $oldPath = $archive->archivePath;
        self::assertNotNull($oldPath);
        $csv = $f->export()->execute(1, $archive->publicId)->content;
        $f->base->at('2026-10-29 09:00:00');
        $f->archives->failRemember = true;
        try {
            $f->renew()->execute(1, $archive->publicId, 'review-expiry-renew-0001');
            self::fail('A failed key INSERT must roll back cleanup metadata as well.');
        } catch (ProductionStorageException) {
            self::assertSame($oldPath, $this->saved($f, $archive->publicId)->archivePath);
            self::assertNull($this->saved($f, $archive->publicId)->obsoletePath);
        }
        $f->archives->failRemember = false;
        $renewed = $f->renew()->execute(1, $archive->publicId, 'review-expiry-renew-0001');
        self::assertSame($renewed->generation, $f->renew()->execute(1, $archive->publicId, 'review-expiry-renew-0001')->generation);
        self::assertSame($oldPath, $this->saved($f, $archive->publicId)->obsoletePath);

        $backing = $f->base->storage();
        $storage = $this->createStub(ArchiveStorageInterface::class);
        $storage->method('archivePath')->willReturnCallback($backing->archivePath(...));
        $storage->method('absolutePath')->willReturnCallback($backing->absolutePath(...));
        $storage->method('delete')->willReturnCallback(static function(string $path) use ($backing, $oldPath): void {
            if ($oldPath === $path) {
                throw new ProductionStorageException('Injected obsolete file deletion failure.');
            }
            $backing->delete($path);
        });
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $pending = $this->saved($f, $archive->publicId);
            self::assertFalse($f->builder(storage: $storage)->build($pending));
            self::assertSame($oldPath, $this->saved($f, $archive->publicId)->obsoletePath);
            $f->base->at($f->base->now->modify('+5 minutes')->format('Y-m-d H:i:s'));
        }
        self::assertSame(VersionStatusEnum::FAILED, $this->saved($f, $archive->publicId)->status);
        self::assertTrue($backing->exists($oldPath));
        $f->renew()->execute(1, $archive->publicId, 'review-expiry-renew-0002');
        self::assertSame($oldPath, $this->saved($f, $archive->publicId)->obsoletePath);
        self::assertSame(1, $f->run()->execute(20)->built);
        $rebuilt = $this->saved($f, $archive->publicId);
        self::assertFalse($backing->exists($oldPath));
        self::assertNull($rebuilt->obsoletePath);
        self::assertSame($archive->firstReadyAt, $rebuilt->firstReadyAt);
        self::assertSame($csv, $f->export()->execute(1, $archive->publicId)->content);
        self::assertCount(1, glob($f->base->root . '/files/production/*/*.zip'));
    }

    public function testExpiredWorkerCannotPublishOrOverwriteTheNextLease(): void
    {
        $f = $this->fixture();
        $archive = $f->snapshot->capture($f->institutions->find(InstitutionFixture::INSTITUTION));
        self::assertNotNull($archive);
        $other = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
        $writer = $this->createStub(ArchiveWriterInterface::class);
        $writer->method('write')->willReturnCallback(static function(array $files, string $csv, string $path) use ($f, $archive, $other): int {
            $bytes = new ZipArchiveWriter(new AtomicZipWriter())->write($files, $csv, $path);
            $f->base->at('2026-10-15 09:31:00');
            self::assertTrue($f->archives->claim($archive->publicId, $other, $f->base->storage()->archivePath(InstitutionFixture::INSTITUTION, $other), $f->base->now, $f->base->now->modify('+30 minutes')));

            return $bytes;
        });
        self::assertFalse($f->builder($writer)->build($archive));
        self::assertSame(VersionStatusEnum::PENDING, $f->archives->find($archive->publicId)?->status);
        self::assertSame($other, $f->archives->leases[$archive->publicId]['owner']);
        $f->base->at('2026-10-15 10:02:00');
        self::assertSame(1, $f->run()->execute(20)->built);
        self::assertCount(1, $f->archives->history(7));
    }

    public function testAllDirectoryPagesAndEmptyGroupsCreateNoLabZip(): void
    {
        $f = new InstitutionFixture();
        for ($i = 1; $i <= 101; ++$i) {
            $f->base->group(sprintf('11111111-1111-4111-8111-%012d', $i), $i, 7, '2026-10-15 08:00:00');
        }
        $result = $f->run()->execute(20);
        self::assertSame(1, $result->scheduled);
        self::assertSame(0, $result->built);
        $archive = $f->archives->history(7)[0];
        self::assertSame(VersionStatusEnum::EMPTY, $archive->status);
        self::assertCount(101, $archive->groups);
        self::assertSame([], glob($f->base->root . '/files/production/*/*.zip'));
    }

    public function testUnsafeFilenameIsVisibleAndRecoversWithoutClaimingThePaidRowTwice(): void
    {
        $f = $this->fixture();
        $photoId = array_key_first($f->base->names);
        self::assertNotNull($photoId);
        $f->base->names[$photoId] = '../unsafe.jpg';
        self::assertSame(1, $f->run()->execute(20)->failed);
        $state = $f->get()->execute(1, InstitutionFixture::INSTITUTION);
        self::assertSame('failed', $state->state);
        self::assertSame('UNSAFE_FILENAME', $state->error);
        self::assertSame([], $state->versions);
        $f->base->names[$photoId] = 'IMG_0001.jpg';
        $this->first($f);
        self::assertNull($f->get()->execute(1, InstitutionFixture::INSTITUTION)->error);
        self::assertSame(0, $f->run()->execute(20)->scheduled);
        self::assertCount(1, $f->base->archives->includedLines(1, new \DateTimeImmutable('2026-10-15 08:00:00', new \DateTimeZone('UTC'))));
    }

    public function testLargeInstitutionZipStreamsMoreBytesThanThePhpMemoryLimit(): void
    {
        $f = $this->fixture();
        for ($i = 1; $i <= 8; ++$i) {
            $code = 'B' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $f->base->paidLine(ProductionFixture::GROUP, 'large-' . $i, $code, 'print-10', '10x15', 1);
        }
        foreach ($f->base->originalFiles as $id => $original) {
            $stream = fopen($original->absolutePath, 'c+b');
            self::assertNotFalse($stream);
            self::assertTrue(ftruncate($stream, 20 * 1024 * 1024));
            fclose($stream);
            $f->base->originalFiles[$id] = new OriginalFileOutputDto(
                photoId: $id,
                mimeType: 'image/jpeg',
                bytes: 20 * 1024 * 1024,
                relativePath: $original->relativePath,
                absolutePath: $original->absolutePath,
            );
        }
        $archive = $this->first($f);
        self::assertGreaterThan(160 * 1024 * 1024, $archive->bytes);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($f->base->storage()->absolutePath((string)$archive->archivePath)));
        foreach ($f->snapshot->lines($archive) as $line) {
            $stream = $zip->getStream($line->entry);
            self::assertNotFalse($stream);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            fclose($stream);
            self::assertSame(hash_file('sha256', $f->base->originalFiles[$line->photoId]->absolutePath), hash_final($hash));
        }
        $zip->close();
        self::assertLessThan(128 * 1024 * 1024, memory_get_peak_usage(true));
    }
}
