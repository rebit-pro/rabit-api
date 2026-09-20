<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Morefoto\Media\Infrastructure\File\GdPreviewRenderer;
use Morefoto\Media\Infrastructure\File\LocalPrivatePhotoStorage;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PhotoFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/morefoto-media-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700, true));
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function testInspectsSupportedImageUsingServerBytesAndFingerprint(): void
    {
        $file = $this->image('photo.png', 'png');
        $result = (new PhotoFileInspector())->inspect($file, '../portrait.png', filesize($file), null);

        self::assertSame('image/png', $result->mimeType);
        self::assertSame('portrait.png', $result->filename);
        self::assertSame(18, $result->width);
        self::assertSame(12, $result->height);
        self::assertSame(hash_file('sha256', $file), $result->fingerprint);
    }

    public function testRejectsUntrustedFingerprintAndDeclaredSize(): void
    {
        $file = $this->image('photo.jpg', 'jpeg');
        $inspector = new PhotoFileInspector();
        try {
            $inspector->inspect($file, 'photo.jpg', filesize($file) + 1, null);
            self::fail('Declared byte mismatch must be rejected.');
        } catch (HttpException $error) {
            self::assertSame('INVALID_PHOTO_SIZE', $error->getMessage());
        }
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('FINGERPRINT_MISMATCH');
        $inspector->inspect($file, 'photo.jpg', filesize($file), str_repeat('0', 64));
    }

    public function testPrivateStorageUsesDeterministicNonPublicPath(): void
    {
        $file = $this->image('photo.png', 'png');
        $fingerprint = hash_file('sha256', $file);
        self::assertIsString($fingerprint);
        $storage = new LocalPrivatePhotoStorage($this->directory . '/private');
        $photo = new InspectedPhoto($file, 'photo.png', 'image/png', filesize($file), 18, 12, $fingerprint);
        $relative = $storage->store('12345678-abcd-4abc-8abc-123456789abc', $photo);

        self::assertSame('12345678-abcd-4abc-8abc-123456789abc/' . substr($fingerprint, 0, 2) . '/' . $fingerprint . '.png', $relative);
        self::assertFileExists($storage->absolutePath($relative));
        self::assertSame(0600, fileperms($storage->absolutePath($relative)) & 0777);
        self::assertSame($relative, $storage->store('12345678-abcd-4abc-8abc-123456789abc', $photo));
    }

    public function testRendererWritesOnlyWatermarkedWebpVariants(): void
    {
        if (!function_exists('imagewebp')) {
            self::markTestSkipped('GD WebP support is required.');
        }
        $file = $this->image('photo.png', 'png', 320, 200);
        $renderer = new GdPreviewRenderer($this->directory . '/public', '/protected-previews');
        $result = $renderer->render($file, 'image/png', '12345678-abcd-4abc-8abc-123456789abc');

        self::assertSame('/protected-previews/12/12345678-abcd-4abc-8abc-123456789abc-thumb.webp', $result->thumbSrc);
        self::assertSame('/protected-previews/12/12345678-abcd-4abc-8abc-123456789abc-preview.webp', $result->previewSrc);
        self::assertFileExists($this->directory . '/public/12/12345678-abcd-4abc-8abc-123456789abc-thumb.webp');
        self::assertFileExists($this->directory . '/public/12/12345678-abcd-4abc-8abc-123456789abc-preview.webp');
    }

    private function image(string $name, string $format, int $width = 18, int $height = 12): string
    {
        $path = $this->directory . '/' . $name;
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(\GdImage::class, $image);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 100, 180));
        $written = match ($format) {
            'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
            default => false,
        };
        imagedestroy($image);
        self::assertTrue($written);

        return $path;
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->remove($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
