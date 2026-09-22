<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Gallery\Mapper\GalleryAssignmentMapper;
use Morefoto\Media\Application\Gallery\Service\ChildPhotos;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/morefoto.commerce/tests/bitrix-result.php';

/**
 * @internal
 */
final class ChildPhotosTest extends TestCase
{
    public function testReadsOnlyValidChildrenOnceAndBuildsFrameCodes(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls([
            'ASSIGNMENT_ID' => 'assignment-1', 'PHOTO_ID' => 'photo-1', 'CHILD_ID' => 'child-1', 'NATIVE_CHILD_ID' => '7',
            'CODE' => 'AB', 'SEQUENCE_NO' => '3', 'UF_WIDTH' => '1200', 'UF_HEIGHT' => '800', 'UF_REVISION' => '2',
        ], false);
        $photos = $this->createMock(GalleryPhotoRepository::class);
        $photos->expects(self::once())->method('children')->with(20, [7, 8])->willReturn($result);

        $items = (new ChildPhotos($photos, new GalleryAssignmentMapper()))->ready(20, [7, 0, 8, 7, -1]);

        self::assertCount(1, $items);
        self::assertSame('AB003', $items[0]->code);
        self::assertSame(7, $items[0]->nativeChildId);
        self::assertSame('assignment-1', $items[0]->assignmentId);
    }

    public function testEmptyChildListDoesNotQueryStorage(): void
    {
        $photos = $this->createMock(GalleryPhotoRepository::class);
        $photos->expects(self::never())->method('children');

        self::assertSame([], (new ChildPhotos($photos, new GalleryAssignmentMapper()))->ready(20, [0]));
    }
}
