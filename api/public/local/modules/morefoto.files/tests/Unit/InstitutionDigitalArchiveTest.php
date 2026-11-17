<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Application\Institution\Contract\DigitalArchivePublisherInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveBuild;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveSnapshot;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;
use Morefoto\Files\Application\Institution\UseCase\CreateInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\DownloadInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ExportInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ListInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Application\Institution\UseCase\MaintainInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Service\DigitalArchiveLayout;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Morefoto\Files\Infrastructure\Database\DigitalArchiveCodec;
use Morefoto\Files\Infrastructure\File\DigitalArchiveWriter;
use Morefoto\Files\Infrastructure\File\LocalProtectedStorage;
use Morefoto\Files\Infrastructure\Id\DownloadIdGenerator;
use Morefoto\Files\Infrastructure\Security\HmacDownloadToken;
use Morefoto\Files\Tests\Unit\Support\InMemoryDigitalArchives;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\Dto\PurchasedDigitalLineOutputDto;
use Rebit\Share\Contracts\Commerce\PurchasedDigitalLinesInterface;
use Rebit\Share\Contracts\Media\BoundedChildPhotosInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\PrintInstitutionOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class InstitutionDigitalArchiveTest extends TestCase
{
    private const string INSTITUTION = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const string GROUP = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const string P1 = '11111111-1111-4111-8111-111111111111';
    private const string P2 = '22222222-2222-4222-8222-222222222222';
    private const string P3 = '33333333-3333-4333-8333-333333333333';
    private string $root;
    private \DateTimeImmutable $now;
    private InMemoryDigitalArchives $archives;
    private DigitalArchiveAccess $access;
    private ClockInterface $clock;
    private OriginalFilesInterface $originals;
    private DigitalArchivePublisherInterface $publisher;
    private LocalProtectedStorage $storage;
    private DigitalArchiveSnapshot $snapshot;
    private bool $identityUnavailable = false;
    /** @var list<PurchasedDigitalLineOutputDto> */
    private array $lines = [];
    /** @var list<GalleryAssignmentOutputDto> */
    private array $photos = [];
    /** @var array<string, OriginalFileOutputDto> */
    private array $originalFiles = [];
    /** @var array<string, string> */
    private array $names = [];
    /** @var array<int, InstitutionScopeOutputDto> */
    private array $scopes = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/institution-digital-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->now = new \DateTimeImmutable('2026-11-17 12:00:00', new \DateTimeZone('UTC'));
        $this->archives = new InMemoryDigitalArchives();
        $this->scopes = [1 => new InstitutionScopeOutputDto('organizer', 1, []), 2 => new InstitutionScopeOutputDto('curator', 1, [7]), 3 => new InstitutionScopeOutputDto('curator', 1, [8])];
        $scope = $this->createStub(InstitutionAccessInterface::class);
        $scope->method('scope')->willReturnCallback(function(int $id): InstitutionScopeOutputDto {
            if ($this->identityUnavailable) {
                throw new HttpException('IDENTITY_UNAVAILABLE', 503);
            }

            return $this->scopes[$id] ?? throw new HttpException('FORBIDDEN', 403);
        });
        $directory = $this->createStub(PrintInstitutionDirectoryInterface::class);
        $directory->method('find')->willReturnCallback(static fn(string $id): ?PrintInstitutionOutputDto => self::INSTITUTION === $id ? new PrintInstitutionOutputDto(7, $id, 'Учреждение') : null);
        $this->access = new DigitalArchiveAccess($scope, $directory, $this->archives);
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturnCallback(fn(): \DateTimeImmutable => $this->now);
        $groups = $this->createStub(GroupDirectoryInterface::class);
        $groups->method('page')->willReturn(new GroupDirectoryPageOutputDto([
            new GroupDirectoryItemOutputDto(
                nativeId: 9,
                id: self::GROUP,
                name: 'Группа',
                kind: 'children',
                institutionNativeId: 7,
                institutionId: self::INSTITUTION,
                institutionName: 'Учреждение',
                shootNativeId: 10,
                shootId: 'shoot',
                shootName: 'Съёмка',
                calendar: new GroupCalendarOutputDto(
                    timezone: 'Europe/Moscow',
                    sentAt: null,
                    closesAt: null,
                    digitalSalesUntil: null,
                    deliveryDueAt: null,
                    status: 'open',
                ),
            ),
        ], 1));
        $purchases = $this->createMock(PurchasedDigitalLinesInterface::class);
        $purchases->method('forInstitution')->with(7, $this->now)->willReturnCallback(fn(): array => $this->lines);
        $children = $this->createStub(BoundedChildPhotosInterface::class);
        $children->method('ready')->willReturnCallback(fn(int $shoot, array $ids, int $limit): array => 10 === $shoot
            ? array_slice(array_values(array_filter($this->photos, static fn(GalleryAssignmentOutputDto $photo): bool => in_array($photo->nativeChildId, $ids, true))), 0, $limit) : []);
        $names = $this->createStub(PhotoFilenamesInterface::class);
        $names->method('filenames')->willReturnCallback(fn(array $ids): array => array_intersect_key($this->names, array_fill_keys($ids, true)));
        $this->originals = $this->createStub(OriginalFilesInterface::class);
        $this->originals->method('originals')->willReturnCallback(fn(array $ids): array => array_intersect_key($this->originalFiles, array_fill_keys($ids, true)));
        $this->publisher = $this->createStub(DigitalArchivePublisherInterface::class);
        $this->storage = new LocalProtectedStorage($this->root . '/archives');
        $this->snapshot = new DigitalArchiveSnapshot($groups, $purchases, $children, $names, $this->originals, new DigitalArchiveLayout(), new DigitalArchivePolicy());
        $this->photo(self::P1, '0001.JPG', 11, 'first original');
        $this->photo(self::P2, '0002.JPG', 11, 'second original');
        $this->photo(self::P3, '0003.JPG', 12, 'other child original');
        $this->lines = [$this->line('digital-a', 'digital', self::P1), $this->line('bundle-a', 'bundle', null)];
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testSnapshotExpandsOnlyBoughtChildAndDeduplicatesOverlappingPurchases(): void
    {
        $files = $this->snapshot->capture(7, self::INSTITUTION, $this->now);
        self::assertSame([self::P1, self::P2], array_column($files, 'photoId'));
        self::assertSame(['digital', 'bundle'], array_column($files[0]->sources, 'kind'));
        self::assertSame('0001.JPG', $files[0]->filename);
        self::assertSame('Группа [' . self::GROUP . ']/0001.JPG', $files[0]->entryName);
        self::assertStringNotContainsString('шт/', $files[0]->entryName);
        $codec = new DigitalArchiveCodec();
        self::assertEquals($files, $codec->decode($codec->encode($files)));
    }

    public function testSameOriginalNamesPreserveBasenameAndNeverOverwrite(): void
    {
        $this->names[self::P2] = '0001.JPG';
        $archive = $this->create();
        $this->builder()->execute($archive->id);
        $ready = $this->archives->find($archive->id);
        self::assertNotNull($ready);
        self::assertSame('ready', $ready->status);
        self::assertNotSame($ready->files[0]->entryName, $ready->files[1]->entryName);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->storage->absoluteArchivePath((string)$ready->archivePath)));
        self::assertSame(3, $zip->numFiles);
        foreach ($ready->files as $file) {
            self::assertSame('0001.JPG', basename($file->entryName));
            self::assertSame(file_get_contents($this->originalFiles[$file->photoId]->absolutePath), $zip->getFromName($file->entryName));
        }
        $manifest = (new DigitalArchiveWriter())->manifest($ready);
        self::assertSame($manifest, $zip->getFromName('manifest.csv'));
        $zip->close();
    }

    public function testPersistedCompositionDoesNotGrowDuringBuild(): void
    {
        $archive = $this->create();
        $this->photos[] = new GalleryAssignmentOutputDto(assignmentId: 'later', photoId: self::P3, childId: 'child', nativeChildId: 11, childCode: 'A', code: 'A003', width: 10, height: 10, revision: 1);
        $this->lines[] = $this->line('later-digital', 'digital', self::P3);
        $this->builder()->execute($archive->id);
        $stored = $this->archives->find($archive->id) ?? throw new \LogicException('Saved archive is missing.');
        self::assertCount(2, $stored->files);
        self::assertCount(3, $this->snapshot->capture(7, self::INSTITUTION, $this->now));
    }

    public function testMissingOriginalCannotBecomeReadyOrPartiallyDownloadable(): void
    {
        $archive = $this->create();
        unset($this->originalFiles[self::P2]);
        $this->builder()->execute($archive->id);
        $failed = $this->archives->find($archive->id);
        self::assertNotNull($failed);
        self::assertSame(['failed', 'ORIGINAL_MISSING', null], [$failed->status, $failed->error, $failed->archivePath]);
        self::assertCount(2, $failed->files);
        $this->expectExceptionMessage('ARCHIVE_NOT_FOUND');
        $this->download()->link(1, $archive->id);
    }

    public function testMissingOriginalAtSnapshotStillRecordsItsRequiredFile(): void
    {
        unset($this->originalFiles[self::P2]);
        $archive = $this->create();
        self::assertNull($archive->files[1]->relativePath);
        $this->builder()->execute($archive->id);
        self::assertSame('ORIGINAL_MISSING', $this->archives->find($archive->id)?->error);
    }

    public function testSourceBytesChangingAfterSnapshotFailsExplicitly(): void
    {
        $archive = $this->create();
        $file = $this->originalFiles[self::P1];
        $this->originalFiles[self::P1] = new OriginalFileOutputDto(photoId: $file->photoId, mimeType: $file->mimeType, bytes: $file->bytes + 1, relativePath: $file->relativePath, absolutePath: $file->absolutePath);
        $this->builder()->execute($archive->id);
        self::assertSame('COMPOSITION_CHANGED', $this->archives->find($archive->id)?->error);
    }

    public function testLostQueueMessageAndTemporaryFailureRecoverWithoutChangingComposition(): void
    {
        $archive = $this->create();
        $publisher = $this->createMock(DigitalArchivePublisherInterface::class);
        $publisher->expects(self::once())->method('build')->with($archive->id, 0);
        self::assertSame(1, $this->maintain($publisher)->dispatch(10)->processed);
        $writer = $this->createStub(DigitalArchiveWriterInterface::class);
        $writer->method('manifest')->willReturn((new DigitalArchiveWriter())->manifest($archive));
        $writer->method('build')->willThrowException(new FilesStorageException('temporary'));
        $this->builder($writer)->execute($archive->id);
        self::assertSame('pending', $this->archives->find($archive->id)?->status);
        $this->now = $this->now->modify('+61 seconds');
        $this->builder()->execute($archive->id);
        $ready = $this->archives->find($archive->id);
        self::assertSame(['ready', 2], [$ready->status, $ready->attempts]);
        self::assertCount(2, $ready->workPaths);
        self::assertEquals($archive->files, $ready->files);
        self::assertFalse($this->archives->ready($archive->id, 'stale-owner', 'bad.zip', 1, $this->now, $this->now));
    }

    public function testReadyLifetimeIsSevenDaysFromCompletionAndPurgeKeepsManifestAndOriginals(): void
    {
        $archive = $this->create();
        $this->now = $this->now->modify('+1 hour');
        $this->builder()->execute($archive->id);
        $ready = $this->archives->find($archive->id);
        self::assertNotNull($ready);
        self::assertEquals($this->now->modify('+7 days'), $ready->expiresAt);
        $manifest = (new ExportInstitutionDigitalArchiveUseCase($this->access, new DigitalArchiveWriter()))->execute(2, $archive->id)->content;
        $path = $this->storage->absoluteArchivePath((string)$ready->archivePath);
        self::assertFileExists($path);
        $this->now = $this->now->modify('+7 days');
        self::assertSame('expired', (new DigitalArchiveView($this->clock))->describe($ready)->status);
        self::assertSame(1, $this->maintain()->purge(10)->processed);
        self::assertFileDoesNotExist($path);
        self::assertSame($manifest, (new ExportInstitutionDigitalArchiveUseCase($this->access, new DigitalArchiveWriter()))->execute(2, $archive->id)->content);
        self::assertFileExists($this->originalFiles[self::P1]->absolutePath);
    }

    public function testReplayReturnsSameSnapshotAndConcurrentNewKeyIsRejected(): void
    {
        $archive = $this->create();
        self::assertSame($archive->id, $this->creator()->execute(1, self::INSTITUTION, str_repeat('k', 32))->id);
        self::assertCount(1, $this->archives->records);
        $this->expectExceptionMessage('DIGITAL_ARCHIVE_IN_PROGRESS');
        $this->creator()->execute(1, self::INSTITUTION, str_repeat('n', 32));
    }

    public function testEmptySnapshotIsPersistedWithoutCreatingZip(): void
    {
        $this->lines = [];
        $archive = $this->create();
        self::assertSame('empty', $archive->status);
        $this->builder()->execute($archive->id);
        self::assertSame([], $this->archives->find($archive->id)?->workPaths);
    }

    public function testCuratorCanReadButCannotCreateAndForeignCuratorCannotRead(): void
    {
        $archive = $this->create();
        $listing = new ListInstitutionDigitalArchivesUseCase($this->access, $this->archives, new DigitalArchiveView($this->clock));
        self::assertFalse($listing->execute(2, self::INSTITUTION)->canCreate);
        self::assertSame($archive->id, $this->access->archive(2, $archive->id)->id);
        foreach ([2, 3] as $actor) {
            try {
                $this->creator()->execute($actor, self::INSTITUTION, str_repeat('c', 32));
                self::fail('Curator created archive.');
            } catch (HttpException $error) {
                self::assertSame('FORBIDDEN', $error->getMessage());
            }
        }
        $this->expectExceptionMessage('INSTITUTION_NOT_FOUND');
        $this->access->archive(3, $archive->id);
    }

    public function testTicketCannotBeTransferredAndRechecksCurrentAssignments(): void
    {
        $archive = $this->create();
        $this->builder()->execute($archive->id);
        $download = $this->download();
        $link = $download->link(2, $archive->id);
        self::assertEquals($this->now->modify('+2 minutes'), $link->expiresAt);
        self::assertStringStartsWith('/_protected/files/institution-digital/', $download->open($link->ticket)->internalUri);
        $changed = str_replace($archive->id . '.2.', $archive->id . '.1.', $link->ticket);
        try {
            $download->open($changed);
            self::fail('Transferred ticket accepted.');
        } catch (HttpException $error) {
            self::assertSame('ARCHIVE_NOT_FOUND', $error->getMessage());
        }
        $this->scopes[2] = new InstitutionScopeOutputDto('curator', 2, []);
        $this->expectExceptionMessage('ARCHIVE_NOT_FOUND');
        $download->open($link->ticket);
    }

    public function testTicketExpiresAtBoundary(): void
    {
        $archive = $this->create();
        $this->builder()->execute($archive->id);
        $link = $this->download()->link(1, $archive->id);
        $this->now = $link->expiresAt;
        $this->expectExceptionMessage('ARCHIVE_NOT_FOUND');
        $this->download()->open($link->ticket);
    }

    public function testRevokedInitiatorCannotBuild(): void
    {
        $archive = $this->create();
        unset($this->scopes[1]);
        $this->builder()->execute($archive->id);
        self::assertSame('ARCHIVE_ACCESS_REVOKED', $this->archives->find($archive->id)?->error);
    }

    public function testUnavailableIdentitySourceRetriesWithoutClaimingAccessWasRevoked(): void
    {
        $archive = $this->create();
        $this->identityUnavailable = true;
        $this->builder()->execute($archive->id);
        $pending = $this->archives->find($archive->id);
        self::assertNotNull($pending);
        self::assertSame('pending', $pending->status);
        self::assertNull($pending->error);
        $this->identityUnavailable = false;
        $this->now = $this->now->modify('+61 seconds');
        $this->builder()->execute($archive->id);
        self::assertSame('ready', $this->archives->find($archive->id)?->status);
    }

    public function testUnsafeNamesAndOversizedCompositionFailExplicitly(): void
    {
        $this->names[self::P1] = '../0001.JPG';
        try {
            $this->snapshot->capture(7, self::INSTITUTION, $this->now);
            self::fail('Unsafe filename accepted.');
        } catch (DigitalArchiveException $error) {
            self::assertSame('INVALID_ORIGINAL_FILENAME', $error->getMessage());
        }
        foreach ([[10_001, 1, 1], [1, DigitalArchivePolicy::MAX_BYTES + 1, 1], [1, 1, 50_001]] as [$files, $bytes, $sources]) {
            try {
                (new DigitalArchivePolicy())->assertFits($files, $bytes, $sources);
                self::fail('Oversized composition accepted.');
            } catch (DigitalArchiveException $error) {
                self::assertSame('DIGITAL_ARCHIVE_TOO_LARGE', $error->getMessage());
            }
        }
    }

    public function testFileCannotCollideWithAnotherPhotosDirectory(): void
    {
        $layout = new DigitalArchiveLayout();
        $names = [self::P1 => 'same.jpg', self::P2 => 'SAME.JPG', self::P3 => self::P1];
        $nested = $layout->collisions($names);
        self::assertCount(3, $nested);
        self::assertSame('group/' . self::P3 . '/' . self::P1, $layout->entry('group', self::P3, self::P1, $nested));
    }

    public function testBundleSentinelRejectsOversizedAssignmentSetBeforeOriginalLookup(): void
    {
        $this->photos = [];
        for ($index = 0; 50_001 > $index; ++$index) {
            $this->photos[] = new GalleryAssignmentOutputDto(assignmentId: 'assignment-' . $index, photoId: 'photo-' . $index, childId: 'child', nativeChildId: 11, childCode: 'A', code: 'A001', width: 10, height: 10, revision: 1);
        }
        $this->expectExceptionMessage('DIGITAL_ARCHIVE_TOO_LARGE');
        $this->snapshot->capture(7, self::INSTITUTION, $this->now);
    }

    private function creator(): CreateInstitutionDigitalArchiveUseCase
    {
        return new CreateInstitutionDigitalArchiveUseCase($this->access, $this->archives, $this->snapshot, new DigitalArchiveView($this->clock), new DownloadIdGenerator(), $this->publisher, $this->clock, new NullLogger());
    }

    private function create(): DigitalArchive
    {
        $id = $this->creator()->execute(1, self::INSTITUTION, str_repeat('k', 32))->id;

        return $this->archives->find($id) ?? throw new \LogicException('Archive not persisted.');
    }

    private function builder(?DigitalArchiveWriterInterface $writer = null): DigitalArchiveBuild
    {
        return new DigitalArchiveBuild(
            $this->archives,
            $this->access,
            $this->originals,
            $writer ?? new DigitalArchiveWriter(),
            $this->storage,
            new DigitalArchivePolicy(),
            new DownloadIdGenerator(),
            $this->clock,
            new NullLogger(),
        );
    }

    private function maintain(?DigitalArchivePublisherInterface $publisher = null): MaintainInstitutionDigitalArchivesUseCase
    {
        return new MaintainInstitutionDigitalArchivesUseCase($this->archives, $publisher ?? $this->publisher, $this->storage, $this->clock, new NullLogger());
    }

    private function download(): DownloadInstitutionDigitalArchiveUseCase
    {
        return new DownloadInstitutionDigitalArchiveUseCase($this->access, new HmacDownloadToken(str_repeat('s', 32)), $this->storage, new DigitalArchiveWriter(), $this->clock);
    }

    private function line(string $id, string $kind, ?string $photoId): PurchasedDigitalLineOutputDto
    {
        return new PurchasedDigitalLineOutputDto(
            orderId: 'order',
            orderNumber: 'MF-000001',
            lineId: $id,
            groupId: self::GROUP,
            shootId: 10,
            childId: 11,
            childCode: 'A',
            kind: $kind,
            photoId: $photoId,
        );
    }

    private function photo(string $id, string $name, int $child, string $content): void
    {
        $path = $this->root . '/' . $id . '.jpg';
        file_put_contents($path, $content);
        $this->originalFiles[$id] = new OriginalFileOutputDto(photoId: $id, mimeType: 'image/jpeg', bytes: strlen($content), relativePath: $id . '.jpg', absolutePath: $path);
        $this->names[$id] = $name;
        $this->photos[] = new GalleryAssignmentOutputDto(assignmentId: 'assignment-' . $id, photoId: $id, childId: 'child-' . $child, nativeChildId: $child, childCode: 'A', code: 'A001', width: 10, height: 10, revision: 1);
    }
}
