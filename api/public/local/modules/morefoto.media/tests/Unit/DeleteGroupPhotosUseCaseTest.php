<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Contract\OriginalFileLockInterface;
use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\DeletePhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\DeletionMutationOutputDto;
use Morefoto\Media\Application\Photo\UseCase\DeleteGroupPhotosUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupReferenceOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class DeleteGroupPhotosUseCaseTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO = '32345678-abcd-4abc-8abc-123456789abc';
    private const string OTHER = '42345678-abcd-4abc-8abc-123456789abc';

    private bool $committed = false;

    /** @var list<string> paths whose original lock is held right now */
    private array $locked = [];

    public function testDeletesPhotosAndRemovesTheirFilesOnlyAfterTheCommit(): void
    {
        $media = $this->prepared(4);
        $media->expects(self::once())->method('deletablePhotos')->with(2, 3, [self::PHOTO, self::OTHER])->willReturn([
            ['id' => 10, 'publicId' => self::PHOTO, 'originalPath' => 'shoot/aa/own.jpg'],
            ['id' => 11, 'publicId' => self::OTHER, 'originalPath' => 'shoot/bb/shared.jpg'],
        ]);
        $media->expects(self::once())->method('deletePhotos')->with([10, 11])
            ->willReturnCallback(fn() => self::assertFalse($this->committed))
        ;
        $media->expects(self::once())->method('advanceRevision')->with(2, 4)->willReturn(5);
        $media->expects(self::once())->method('saveIdempotency')
            ->with(4, '/groups/' . self::GROUP . '/photo-deletions', self::anything(), self::anything(), '{"deleted":2,"revision":5}')
        ;
        $photos = $this->createStub(PhotoRepository::class);
        // The check runs under the lock of its path, where an upload of the same content registers its row.
        $photos->method('originalPathInUse')->willReturnCallback(function(string $path): bool {
            self::assertSame([$path], $this->locked);

            return 'shoot/bb/shared.jpg' === $path;
        });
        $previews = $this->createMock(PreviewRendererInterface::class);
        $removed = [];
        $previews->expects(self::exactly(2))->method('remove')->willReturnCallback(function(string $photoId) use (&$removed): void {
            self::assertTrue($this->committed);
            $removed[] = $photoId;
        });
        $storage = $this->createMock(PrivatePhotoStorageInterface::class);
        $storage->expects(self::once())->method('delete')->with('shoot/aa/own.jpg')
            ->willReturnCallback(fn() => self::assertSame(['shoot/aa/own.jpg'], $this->locked))
        ;

        $output = $this->useCase($media, $photos, $storage, $previews)->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO, self::OTHER]));

        self::assertEquals(new DeletionMutationOutputDto(2, 5), $output);
        self::assertSame([self::PHOTO, self::OTHER], $removed);
    }

    public function testSentGroupIsRejectedBeforeTheLock(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::never())->method('lockRevision');
        $useCase = $this->useCase($media, scopes: $this->scopes(false));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));
    }

    public function testDeletionWaitingForTheLinkDeliveryIsRejectedAfterTheLock(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->with(2)->willReturn(4);
        $media->expects(self::never())->method('deletePhotos');
        $useCase = $this->useCase($media, scopes: $this->scopes(true, false));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));
    }

    public function testStaleRevisionDeletesNothing(): void
    {
        $media = $this->prepared(5);
        $media->expects(self::never())->method('deletablePhotos');
        $media->expects(self::never())->method('deletePhotos');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('REVISION_CONFLICT');
        $this->useCase($media)->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));
    }

    public function testRepeatedKeyReturnsTheStoredResultWithoutDeletingAgain(): void
    {
        $hash = hash('sha256', json_encode(['revision' => 4, 'photoIds' => [self::PHOTO]], JSON_THROW_ON_ERROR));
        $media = $this->prepared(5, ['PAYLOAD_HASH' => $hash, 'RESULT_JSON' => '{"deleted":1,"revision":5}']);
        $media->expects(self::never())->method('deletePhotos');
        $previews = $this->createMock(PreviewRendererInterface::class);
        $previews->expects(self::never())->method('remove');

        $output = $this->useCase($media, previews: $previews)->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));

        self::assertEquals(new DeletionMutationOutputDto(1, 5), $output);
    }

    public function testRepeatedKeyWithAnotherSetIsAConflict(): void
    {
        $media = $this->prepared(5, ['PAYLOAD_HASH' => str_repeat('0', 64), 'RESULT_JSON' => '{"deleted":1,"revision":5}']);
        $media->expects(self::never())->method('deletePhotos');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $this->useCase($media)->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));
    }

    public function testLeftoverFilesAreLoggedWithoutFailingTheCommittedDeletion(): void
    {
        $media = $this->prepared(4);
        $media->method('deletablePhotos')->willReturn([['id' => 10, 'publicId' => self::PHOTO, 'originalPath' => null]]);
        $media->method('advanceRevision')->willReturn(5);
        $previews = $this->createStub(PreviewRendererInterface::class);
        $previews->method('remove')->willThrowException(new \RuntimeException('disk'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Deleted photo files remain on disk.', ['photoId' => self::PHOTO, 'exception' => \RuntimeException::class]);

        $output = $this->useCase($media, previews: $previews, logger: $logger)->execute(4, self::GROUP, $this->key(), new DeletePhotosInputDto(4, [self::PHOTO]));

        self::assertSame(1, $output->deleted);
    }

    /** @param array{PAYLOAD_HASH: string, RESULT_JSON: string}|false $stored */
    private function prepared(int $revision, array|false $stored = false): MockObject
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($stored);
        $media = $this->createMock(MediaMutationRepository::class);
        $media->method('lockRevision')->with(2)->willReturn($revision);
        $media->method('idempotency')->willReturn($result);

        return $media;
    }

    private function useCase(
        MockObject $media,
        ?PhotoRepository $photos = null,
        ?PrivatePhotoStorageInterface $storage = null,
        ?PreviewRendererInterface $previews = null,
        ?MediaScopeInterface $scopes = null,
        ?LoggerInterface $logger = null,
    ): DeleteGroupPhotosUseCase {
        $groups = $this->createStub(GroupReferenceInterface::class);
        $groups->method('get')->willReturn(new GroupReferenceOutputDto(3, self::GROUP, self::SHOOT, 'regular'));

        self::assertInstanceOf(MediaMutationRepository::class, $media);

        return new DeleteGroupPhotosUseCase(
            $this->transaction(),
            $media,
            $photos ?? $this->createStub(PhotoRepository::class),
            $groups,
            $scopes ?? $this->scopes(true),
            $this->createStub(AccessGuardInterface::class),
            $storage ?? $this->createStub(PrivatePhotoStorageInterface::class),
            $this->originals(),
            $previews ?? $this->createStub(PreviewRendererInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function scopes(bool ...$editable): MediaScopeInterface
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturnOnConsecutiveCalls(...array_values(array_map(
            static fn(bool $value): MediaScopeOutputDto => new MediaScopeOutputDto(
                institutionId: 1,
                shootId: 2,
                shootPublicId: self::SHOOT,
                groupId: 3,
                groupPublicId: self::GROUP,
                groupEditable: $value,
            ),
            [...$editable, ...$editable],
        )));

        return $scopes;
    }

    private function key(): IdempotencyKey
    {
        return new IdempotencyKey(str_repeat('c', 32));
    }

    private function transaction(): MediaTransactionInterface
    {
        return new class(function(): void {
            $this->committed = true;
        }) implements MediaTransactionInterface {
            public function __construct(private readonly \Closure $commit) {}

            public function execute(callable $operation): mixed
            {
                $result = $operation();
                ($this->commit)();

                return $result;
            }
        };
    }

    private function originals(): OriginalFileLockInterface
    {
        return new class(function(?string $path): void {
            $this->locked = null === $path ? [] : [$path];
        }) implements OriginalFileLockInterface {
            public function __construct(private readonly \Closure $hold) {}

            public function synchronized(string $originalPath, callable $operation): mixed
            {
                ($this->hold)($originalPath);
                try {
                    return $operation();
                } finally {
                    ($this->hold)(null);
                }
            }
        };
    }
}
