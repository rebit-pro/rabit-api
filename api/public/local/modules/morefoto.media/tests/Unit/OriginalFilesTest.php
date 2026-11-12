<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Service\OriginalFiles;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\File\LocalPrivatePhotoStorage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 3) . '/morefoto.commerce/tests/bitrix-result.php';

/**
 * @internal
 */
final class OriginalFilesTest extends TestCase
{
    private const string PHOTO = '0f8c1d52-8d44-4f0e-9a52-2b8f5f1f0a11';

    public function testReturnsOnlyStoredOriginalsKeyedByPhoto(): void
    {
        $root = sys_get_temp_dir() . '/mf-originals-' . bin2hex(random_bytes(4));
        mkdir($root . '/shoot/ab', 0700, true);
        file_put_contents($root . '/shoot/ab/ab.jpg', 'photo');
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls([
            'UF_PUBLIC_ID' => self::PHOTO, 'UF_MIME_TYPE' => 'image/jpeg', 'UF_BYTES' => '2048', 'UF_ORIGINAL_PATH' => 'shoot/ab/ab.jpg',
        ], false);
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::once())->method('originals')->with([self::PHOTO])->willReturn($result);

        try {
            $originals = new OriginalFiles($photos, new LocalPrivatePhotoStorage($root))->originals([self::PHOTO, '../etc', self::PHOTO]);

            self::assertSame([self::PHOTO], array_keys($originals));
            self::assertSame(['image/jpeg', 2048, 'shoot/ab/ab.jpg', $root . '/shoot/ab/ab.jpg'], [
                $originals[self::PHOTO]->mimeType, $originals[self::PHOTO]->bytes, $originals[self::PHOTO]->relativePath, $originals[self::PHOTO]->absolutePath,
            ]);
        } finally {
            unlink($root . '/shoot/ab/ab.jpg');
            rmdir($root . '/shoot/ab');
            rmdir($root . '/shoot');
            rmdir($root);
        }
    }

    public function testMissingStoredOriginalIsNotReturned(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls([
            'UF_PUBLIC_ID' => self::PHOTO, 'UF_MIME_TYPE' => 'image/jpeg', 'UF_BYTES' => '2048', 'UF_ORIGINAL_PATH' => 'shoot/ab/ab.jpg',
        ], false);
        $photos = $this->createStub(PhotoRepository::class);
        $photos->method('originals')->willReturn($result);

        self::assertSame([], new OriginalFiles($photos, new LocalPrivatePhotoStorage('/nonexistent-media'))->originals([self::PHOTO]));
    }

    public function testInvalidIdsDoNotQueryStorage(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::never())->method('originals');

        self::assertSame([], new OriginalFiles($photos, new LocalPrivatePhotoStorage('/srv'))->originals(['', 'x', '../../passwd']));
    }
}
