<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Service\PhotoFilenames;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/morefoto.commerce/tests/bitrix-result.php';

/** @internal */
final class PhotoFilenamesTest extends TestCase
{
    public function testNamesPreserveOriginalBytesAndDoNotRequireAnOriginal(): void
    {
        $id = 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001';
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(['UF_PUBLIC_ID' => $id, 'UF_FILENAME' => '0007_Кадр.JPEG'], false);
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::once())->method('filenames')->with([$id])->willReturn($result);
        $photos->expects(self::never())->method('originals');

        self::assertSame([$id => '0007_Кадр.JPEG'], new PhotoFilenames($photos)->filenames([$id, $id, '../file']));
    }

    public function testInvalidIdsDoNotQueryAndLargeSetsAreBatched(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::exactly(2))->method('filenames')->willReturnCallback(function(array $ids): Result {
            self::assertLessThanOrEqual(500, count($ids));
            $result = $this->createStub(Result::class);
            $result->method('fetch')->willReturn(false);

            return $result;
        });
        $names = new PhotoFilenames($photos);
        self::assertSame([], $names->filenames(['', 'not-a-photo', '../x']));
        $ids = array_map(static fn(int $i): string => sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', $i), range(1, 501));
        self::assertSame([], $names->filenames($ids));
    }
}
