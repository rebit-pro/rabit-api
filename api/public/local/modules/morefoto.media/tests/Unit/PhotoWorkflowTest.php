<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Morefoto\Media\Application\Photo\Message\ProcessPhotoMessage;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
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

    public function testMapperKeepsAllJsonAssignmentsAndPreservesPrimaryOrder(): void
    {
        $encoded = [];
        for ($sortId = 30; 1 <= $sortId; --$sortId) {
            $encoded[] = [
                'childId' => sprintf('child-%02d', $sortId),
                'childCode' => sprintf('C%02d', $sortId),
                'sequence' => 1,
                'sortId' => $sortId,
            ];
        }

        $photo = (new PhotoRowMapper())->map([
            'UF_PUBLIC_ID' => '12345678-abcd-4abc-8abc-123456789abc',
            'UF_STATUS' => 'ready',
            'SHOOT_PUBLIC_ID' => '22345678-abcd-4abc-8abc-123456789abc',
            'GROUP_PUBLIC_ID' => '32345678-abcd-4abc-8abc-123456789abc',
            'ORIGINAL_GROUP_PUBLIC_ID' => '32345678-abcd-4abc-8abc-123456789abc',
            'UF_FILENAME' => 'photo.jpg',
            'UF_BYTES' => 100,
            'UF_WIDTH' => 10,
            'UF_HEIGHT' => 10,
            'UF_FINGERPRINT' => str_repeat('a', 64),
            'UF_REVISION' => 3,
            'ASSIGNMENTS' => json_encode($encoded, JSON_THROW_ON_ERROR),
        ]);

        self::assertCount(30, $photo->assignments);
        self::assertSame(
            array_map(static fn(int $id): string => sprintf('child-%02d', $id), range(1, 30)),
            array_map(
                static fn(PhotoAssignmentOutputDto $assignment): string => $assignment->childId,
                $photo->assignments,
            ),
        );
        self::assertSame('C01', $photo->childCode);
        self::assertSame('C01001', $photo->code);
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
