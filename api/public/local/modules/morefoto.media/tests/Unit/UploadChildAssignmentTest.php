<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Contract\OriginalFileLockInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\PhotoRegistration;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoInputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Application\Photo\Service\UploadChildAssignment;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use Morefoto\Media\Presentation\Photo\Dto\UploadPhotoRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoInputMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class UploadChildAssignmentTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO = '32345678-abcd-4abc-8abc-123456789abc';
    private const string CANONICAL = '42345678-abcd-4abc-8abc-123456789abc';

    private bool $inTransaction = false;

    public function testGroupPhotoIsAssignedToEveryCodeUnderOneRevision(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->with(2)
            ->willReturnCallback(fn(): int => $this->inTransaction ? 7 : self::fail('Revision is locked outside the transaction.'))
        ;
        $media->expects(self::once())->method('groupPhoto')->with(2, 3, self::PHOTO)->willReturn(40);
        $media->expects(self::exactly(3))->method('child')
            ->willReturnCallback(static fn(int $shoot, int $group, string $code): array => ['id' => ['A' => 1, 'B' => 2, 'AA' => 3][$code], 'publicId' => 'child-' . $code])
        ;
        $assigned = [];
        $media->expects(self::exactly(3))->method('assign')->willReturnCallback(static function(int $childId, array $photoIds) use (&$assigned): bool {
            $assigned[$childId] = $photoIds;

            // The photo already belongs to child B from an earlier upload of the same file.
            return 2 !== $childId;
        });
        $media->expects(self::once())->method('advanceRevision')->with(2, 7)->willReturn(8);

        self::assertSame(['A', 'B', 'AA'], $this->service($media, true)->assign($this->scope(), self::PHOTO, ['A', 'B', 'AA']));
        self::assertSame([1 => [40], 2 => [40], 3 => [40]], $assigned);
    }

    public function testRepeatedUploadWithKnownCodesKeepsTheRevision(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->method('lockRevision')->willReturn(7);
        $media->method('groupPhoto')->willReturn(40);
        $media->method('child')->willReturn(['id' => 1, 'publicId' => 'child-A']);
        $media->method('assign')->willReturn(false);
        $media->expects(self::never())->method('advanceRevision');

        self::assertSame(['A'], $this->service($media, true)->assign($this->scope(), self::PHOTO, ['A']));
    }

    public function testPhotoOutsideTheUploadGroupIsNotLabeled(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->method('lockRevision')->willReturn(7);
        $media->expects(self::once())->method('groupPhoto')->willReturn(null);
        $media->expects(self::never())->method('child');
        $media->expects(self::never())->method('assign');
        $media->expects(self::never())->method('advanceRevision');

        self::assertSame([], $this->service($media, true)->assign($this->scope(), self::PHOTO, ['A', 'B']));
    }

    public function testGroupHandedOverWhileWaitingForTheLockRejectsTheLabels(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->willReturn(7);
        $media->expects(self::never())->method('groupPhoto');
        $media->expects(self::never())->method('assign');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_MEDIA_LOCKED');
        $this->service($media, false)->assign($this->scope(), self::PHOTO, ['A']);
    }

    public function testDuplicateUploadLabelsThePhotoThatOwnsTheContent(): void
    {
        $assignment = $this->createMock(UploadChildAssignment::class);
        $assignment->expects(self::once())->method('assign')
            ->with(self::isInstanceOf(MediaScopeOutputDto::class), self::CANONICAL, ['A', 'B'])
            ->willReturn(['A', 'B'])
        ;
        $output = $this->upload(new PhotoRegistration(self::PHOTO, 'duplicate', 1, false, self::CANONICAL), $assignment, ['A', 'B']);

        self::assertSame('duplicate', $output->status);
        self::assertSame(['A', 'B'], $output->childCodes);
    }

    public function testUploadWithoutCodesKeepsThePreviousFlow(): void
    {
        $assignment = $this->createMock(UploadChildAssignment::class);
        $assignment->expects(self::never())->method('assign');

        self::assertSame([], $this->upload(new PhotoRegistration(self::PHOTO, 'processing', 1, true), $assignment, [])->childCodes);
    }

    public function testMapperSplitsCodesAndDropsRepeats(): void
    {
        $input = (new PhotoInputMapper())->upload(new UploadPhotoRequestDto(self::SHOOT, '/tmp/x', 'x.jpg', 1, self::GROUP, null, 'A,B,A,AA'));

        self::assertSame(['A', 'B', 'AA'], $input->childCodes);
        self::assertSame([], (new PhotoInputMapper())->upload(new UploadPhotoRequestDto(self::SHOOT, '/tmp/x', 'x.jpg', 1, self::GROUP, null))->childCodes);
    }

    #[DataProvider('codes')]
    public function testCodesPattern(string $value, bool $valid): void
    {
        self::assertSame($valid, 1 === preg_match(PhotoInputMapper::CHILD_CODES_PATTERN, $value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function codes(): iterable
    {
        yield 'one code' => ['A', true];
        yield 'group of codes' => ['A,B,AA,BK', true];
        yield 'hundred codes' => [implode(',', array_fill(0, 100, 'A')), true];
        yield 'more than hundred' => [implode(',', array_fill(0, 101, 'A')), false];
        yield 'lower case' => ['a', false];
        yield 'cyrillic' => ['А', false];
        yield 'four letters' => ['ABCD', false];
        yield 'empty item' => ['A,,B', false];
        yield 'trailing comma' => ['A,', false];
        yield 'spaces' => ['A, B', false];
        yield 'empty' => ['', false];
        yield 'trailing newline' => ["A\n", false];
    }

    /** @param list<string> $codes */
    private function upload(PhotoRegistration $registration, UploadChildAssignment $assignment, array $codes): UploadPhotoOutputDto
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn($this->scope());
        $storage = $this->createStub(PrivatePhotoStorageInterface::class);
        $storage->method('store')->willReturn('shoot/ab/photo.png');
        $photos = $this->createStub(PhotoRepository::class);
        $photos->method('register')->willReturn($registration);
        $file = (string)tempnam(sys_get_temp_dir(), 'mf-codes');
        $image = imagecreatetruecolor(2, 2);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $file);
        $originals = new class implements OriginalFileLockInterface {
            public function synchronized(string $originalPath, callable $operation): mixed
            {
                return $operation();
            }
        };
        try {
            return (new UploadPhotoUseCase(
                $scopes,
                $this->createStub(AccessGuardInterface::class),
                new PhotoFileInspector(),
                $storage,
                $originals,
                $photos,
                $this->createStub(MediaPublisherInterface::class),
                new NullLogger(),
                $assignment,
            ))->execute(4, new UploadPhotoInputDto(self::SHOOT, self::GROUP, $file, 'photo.png', (int)filesize($file), null, $codes));
        } finally {
            unlink($file);
        }
    }

    private function service(MediaMutationRepository $media, bool $editable): UploadChildAssignment
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(1, 2, self::SHOOT, 3, self::GROUP, $editable));
        $transaction = $this->createStub(MediaTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(function(callable $operation): mixed {
            $this->inTransaction = true;
            try {
                return $operation();
            } finally {
                $this->inTransaction = false;
            }
        });

        return new UploadChildAssignment($transaction, $media, $scopes);
    }

    private function scope(): MediaScopeOutputDto
    {
        return new MediaScopeOutputDto(1, 2, self::SHOOT, 3, self::GROUP, true);
    }
}
