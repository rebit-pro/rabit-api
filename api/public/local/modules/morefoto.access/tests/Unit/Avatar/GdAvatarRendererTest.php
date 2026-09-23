<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Infrastructure\Avatar\AvatarFileInspector;
use Morefoto\Access\Infrastructure\Avatar\GdAvatarRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GdAvatarRendererTest extends TestCase
{
    private AvatarImages $images;

    protected function setUp(): void
    {
        $this->images = new AvatarImages();
    }

    protected function tearDown(): void
    {
        $this->images->cleanup();
    }

    public function testKeepsTheCentreSquareInBothSizes(): void
    {
        // Three vertical stripes: only the red middle one survives the centre square crop.
        $path = $this->images->png(300, 100, static function(\GdImage $image): void {
            imagefilledrectangle($image, 0, 0, 99, 99, (int)imagecolorallocate($image, 0, 160, 0));
            imagefilledrectangle($image, 100, 0, 199, 99, (int)imagecolorallocate($image, 220, 0, 0));
            imagefilledrectangle($image, 200, 0, 299, 99, (int)imagecolorallocate($image, 0, 0, 220));
        });

        $rendered = $this->render($path);

        self::assertSame([256, 256, IMAGETYPE_WEBP], array_slice((array)getimagesizefromstring($rendered->full), 0, 3));
        self::assertSame([64, 64, IMAGETYPE_WEBP], array_slice((array)getimagesizefromstring($rendered->thumb), 0, 3));
        $full = imagecreatefromstring($rendered->full);
        self::assertInstanceOf(\GdImage::class, $full);
        foreach ([[8, 128], [128, 128], [247, 128]] as [$x, $y]) {
            [$red, $green, $blue] = AvatarImages::rgb($full, $x, $y);
            self::assertGreaterThan(180, $red);
            self::assertLessThan(60, $green + $blue);
        }
    }

    public function testAppliesCameraOrientationAndDropsExif(): void
    {
        // Stored left half red, right half blue; orientation 6 shows it turned clockwise: red on top.
        $path = $this->images->jpegWithOrientation(200, 100, 6, static function(\GdImage $image): void {
            imagefilledrectangle($image, 0, 0, 99, 99, (int)imagecolorallocate($image, 220, 0, 0));
            imagefilledrectangle($image, 100, 0, 199, 99, (int)imagecolorallocate($image, 0, 0, 220));
        });
        self::assertSame(6, (int)(exif_read_data($path)['Orientation'] ?? 0));

        $rendered = $this->render($path);

        $full = imagecreatefromstring($rendered->full);
        self::assertInstanceOf(\GdImage::class, $full);
        [$topRed, , $topBlue] = AvatarImages::rgb($full, 128, 40);
        [$bottomRed, , $bottomBlue] = AvatarImages::rgb($full, 128, 216);
        self::assertGreaterThan($topBlue, $topRed);
        self::assertGreaterThan($bottomRed, $bottomBlue);
        foreach ([$rendered->full, $rendered->thumb] as $webp) {
            self::assertStringStartsWith('RIFF', $webp);
            self::assertStringNotContainsString('EXIF', $webp);
            self::assertStringNotContainsString('Exif', $webp);
        }
    }

    private function render(string $path): RenderedAvatarDto
    {
        return (new GdAvatarRenderer())->render((new AvatarFileInspector())->inspect(new UploadedAvatarInputDto($path, 1)));
    }
}
