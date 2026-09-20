<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PhotoWorkflowTest extends TestCase
{
    public function testMessageDeduplicationSeparatesReopenedPhotoRevision(): void
    {
        $first = new ProcessPhotoMessage('12345678-abcd-4abc-8abc-123456789abc', 1);
        $reopened = new ProcessPhotoMessage('12345678-abcd-4abc-8abc-123456789abc', 6);

        self::assertSame('media:12345678-abcd-4abc-8abc-123456789abc:1', $first->getDeduplicationKey());
        self::assertSame('media:12345678-abcd-4abc-8abc-123456789abc:6', $reopened->getDeduplicationKey());
        self::assertNotSame($first->getDeduplicationKey(), $reopened->getDeduplicationKey());
    }

    public function testUploadRejectsPublishedGroupBeforeInspectingOrStoringFile(): void
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(
            institutionId: 1,
            shootId: 2,
            shootPublicId: '12345678-abcd-4abc-8abc-123456789abc',
            groupId: 3,
            groupPublicId: '22345678-abcd-4abc-8abc-123456789abc',
            groupEditable: false,
        ));
        $access = $this->createStub(AccessGuardInterface::class);
        $storage = $this->createStub(PrivatePhotoStorageInterface::class);
        $publisher = $this->createStub(MediaPublisherInterface::class);
        $useCase = new UploadPhotoUseCase(
            $scopes,
            $access,
            new PhotoFileInspector(),
            $storage,
            new PhotoRepository(),
            $publisher,
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_MEDIA_LOCKED');
        $useCase->execute(
            userId: 4,
            shootId: '12345678-abcd-4abc-8abc-123456789abc',
            groupId: '22345678-abcd-4abc-8abc-123456789abc',
            tmpName: '/file-must-not-be-read',
            filename: 'photo.png',
            bytes: 100,
            clientFingerprint: null,
        );
    }
}
