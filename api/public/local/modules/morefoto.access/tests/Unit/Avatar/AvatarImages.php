<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

/** Temporary test images painted with GD; a JPEG can carry an EXIF orientation like a phone photo. */
final class AvatarImages
{
    /** @var list<string> */
    private array $files = [];

    /** @param null|callable(\GdImage, int, int): void $paint */
    public function png(int $width, int $height, ?callable $paint = null): string
    {
        $image = $this->canvas($width, $height, $paint);
        ob_start();
        imagepng($image);

        return $this->file((string)ob_get_clean());
    }

    /** @param callable(\GdImage, int, int): void $paint */
    public function jpegWithOrientation(int $width, int $height, int $orientation, callable $paint): string
    {
        $image = $this->canvas($width, $height, $paint);
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string)ob_get_clean();
        // APP1 "Exif" with a big-endian TIFF header and one IFD0 entry: Orientation (0x0112), SHORT, 1 value.
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00\x00\x00\x00\x00";
        $payload = "Exif\x00\x00" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return $this->file(substr($jpeg, 0, 2) . $segment . substr($jpeg, 2));
    }

    public function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar-test-');
        if (false === $path) {
            throw new \RuntimeException('Cannot create a temporary image.');
        }
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    /** @return array{int, int, int} */
    public static function rgb(\GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    public function cleanup(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->files = [];
    }

    /** @param null|callable(\GdImage, int, int): void $paint */
    private function canvas(int $width, int $height, ?callable $paint): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('Cannot allocate a test image.');
        }
        imagefill($image, 0, 0, (int)imagecolorallocate($image, 200, 200, 200));
        if (null !== $paint) {
            $paint($image, $width, $height);
        }

        return $image;
    }
}
