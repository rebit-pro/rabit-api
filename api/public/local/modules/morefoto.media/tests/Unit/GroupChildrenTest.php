<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Application\Gallery\Service\GroupChildren;
use Morefoto\Media\Domain\Gallery\Repository\GroupChildRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/morefoto.commerce/tests/bitrix-result.php';

/**
 * @internal
 */
final class GroupChildrenTest extends TestCase
{
    public function testMapsChildrenOfValidGroupsWithFirstReadyPhoto(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(
            ['ID' => '7', 'PUBLIC_ID' => 'child-a', 'GROUP_ID' => '30', 'CODE' => 'A', 'PHOTO_ID' => 'photo-1'],
            ['ID' => '8', 'PUBLIC_ID' => 'child-b', 'GROUP_ID' => '30', 'CODE' => 'B', 'PHOTO_ID' => null],
            false,
        );
        $repository = $this->createMock(GroupChildRepository::class);
        $repository->expects(self::once())->method('children')->with([30, 31])->willReturn($result);

        $children = (new GroupChildren($repository, $this->createStub(PreviewContentInterface::class)))->children([30, 0, 31, 30]);

        self::assertCount(2, $children);
        self::assertSame([7, 'child-a', 30, 'A', 'photo-1'], [$children[0]->nativeId, $children[0]->id, $children[0]->groupId, $children[0]->code, $children[0]->photoId]);
        self::assertNull($children[1]->photoId);
    }

    public function testCountsChildrenByGroup(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(['GROUP_ID' => '30', 'CHILDREN' => '12'], false);
        $repository = $this->createMock(GroupChildRepository::class);
        $repository->expects(self::once())->method('counts')->with([30, 31])->willReturn($result);

        self::assertSame([30 => 12], (new GroupChildren($repository, $this->createStub(PreviewContentInterface::class)))->counts([30, 31]));
    }

    public function testEmptyGroupListDoesNotQueryStorage(): void
    {
        $repository = $this->createMock(GroupChildRepository::class);
        $repository->expects(self::never())->method('children');
        $repository->expects(self::never())->method('counts');
        $children = new GroupChildren($repository, $this->createStub(PreviewContentInterface::class));

        self::assertSame([], $children->children([0, -3]));
        self::assertSame([], $children->counts([]));
    }

    public function testMissingThumbnailIsNull(): void
    {
        $previews = $this->createStub(PreviewContentInterface::class);
        $previews->method('read')->willReturnCallback(static function(string $photoId, string $variant): PreviewContentOutputDto {
            self::assertSame('thumb', $variant);
            if ('photo-1' !== $photoId) {
                throw new HttpException('PREVIEW_UNAVAILABLE', 404);
            }

            return new PreviewContentOutputDto('webp');
        });
        $children = new GroupChildren($this->createStub(GroupChildRepository::class), $previews);

        self::assertSame('webp', $children->thumbnail('photo-1')?->content);
        self::assertNull($children->thumbnail('photo-2'));
    }
}
