<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Transfer\Service\ChildTransfers;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Transfer\Repository\ChildTransferRepository;
use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ChildTransfersTest extends TestCase
{
    public function testSetsCarryFrameCodesAndPhotosSharedWithChildrenOutsideTheMove(): void
    {
        $transfers = $this->createStub(ChildTransferRepository::class);
        $transfers->method('children')->willReturn([7 => ['publicId' => 'child-7', 'groupId' => 3, 'code' => 'A']]);
        $transfers->method('assignments')->willReturn([
            ['childId' => 7, 'sequence' => 1, 'photoId' => 101, 'publicId' => 'photo-101', 'revision' => 2, 'status' => 'ready'],
            ['childId' => 7, 'sequence' => 2, 'photoId' => 102, 'publicId' => 'photo-102', 'revision' => 1, 'status' => 'ready'],
        ]);
        $transfers->method('photoChildren')->willReturn([101 => [7], 102 => [7, 8]]);
        $service = new ChildTransfers($this->createStub(MediaMutationRepository::class), $transfers, new ChildTransferPolicy());

        $set = $service->sets(20, [7, 7, 0])[7];

        self::assertSame(3, $set->groupId);
        self::assertSame(['A001', 'A002'], array_map(static fn($photo): string => $photo->code, $set->photos));
        self::assertSame(['A002'], $set->sharedPhotoCodes);
    }

    public function testMoveKeepsIdsReplacesTheMovedCoverAndAdvancesTheRevisionOnce(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->with(20)->willReturn(5);
        $media->expects(self::once())->method('advanceRevision')->with(20, 5)->willReturn(6);
        $transfers = $this->createMock(ChildTransferRepository::class);
        $transfers->method('children')->willReturn([
            7 => ['publicId' => 'child-7', 'groupId' => 3, 'code' => 'A'],
            8 => ['publicId' => 'child-8', 'groupId' => 3, 'code' => 'B'],
        ]);
        $transfers->method('assignments')->willReturn([
            ['childId' => 7, 'sequence' => 1, 'photoId' => 101, 'publicId' => 'photo-101', 'revision' => 1, 'status' => 'ready'],
            ['childId' => 7, 'sequence' => 2, 'photoId' => 102, 'publicId' => 'photo-102', 'revision' => 1, 'status' => 'ready'],
            ['childId' => 8, 'sequence' => 1, 'photoId' => 102, 'publicId' => 'photo-102', 'revision' => 1, 'status' => 'ready'],
        ]);
        $moved = [];
        $transfers->expects(self::exactly(2))->method('moveChild')->willReturnCallback(static function(int $child, int $group, string $code) use (&$moved): void {
            $moved[] = [$child, $group, $code];
        });
        // A photo shared by two moving children moves once.
        $transfers->expects(self::once())->method('movePhotos')->with(20, [101, 102], 9);
        $transfers->method('cover')->willReturnMap([[3, 101], [9, null]]);
        $transfers->expects(self::once())->method('coverCandidate')->with(3)->willReturn(150);
        $covers = [];
        $transfers->expects(self::exactly(2))->method('replaceCover')->willReturnCallback(static function(int $group, ?int $photo) use (&$covers): void {
            $covers[$group] = $photo;
        });
        $service = new ChildTransfers($media, $transfers, new ChildTransferPolicy());

        $revision = $service->move(20, [new ChildMoveInputDto(7, 9, 'C'), new ChildMoveInputDto(8, 9, 'D')]);

        self::assertSame(6, $revision);
        self::assertSame([[7, 9, 'C'], [8, 9, 'D']], $moved);
        self::assertSame([3 => 150, 9 => 101], $covers);
    }

    public function testMoveRejectsTheSameGroupBeforeWriting(): void
    {
        $media = $this->createStub(MediaMutationRepository::class);
        $transfers = $this->createMock(ChildTransferRepository::class);
        $transfers->method('children')->willReturn([7 => ['publicId' => 'child-7', 'groupId' => 3, 'code' => 'A']]);
        $transfers->method('assignments')->willReturn([]);
        $transfers->expects(self::never())->method('moveChild');

        $this->expectException(\InvalidArgumentException::class);
        (new ChildTransfers($media, $transfers, new ChildTransferPolicy()))->move(20, [new ChildMoveInputDto(7, 3, 'B')]);
    }
}
