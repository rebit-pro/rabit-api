<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Avatar;

use Morefoto\Access\Application\Avatar\Contract\AvatarRendererInterface;
use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Turns an inspected upload into square WebP variants: the camera orientation is applied, the centre square is kept,
 * and re-encoding drops EXIF, GPS and colour profiles of the original.
 */
final readonly class GdAvatarRenderer implements AvatarRendererInterface
{
    private const int QUALITY = 82;

    public function render(InspectedAvatarDto $avatar): RenderedAvatarDto
    {
        if (!function_exists('imagewebp')) {
            throw new AccessStorageException('Avatar renderer is unavailable.');
        }
        $source = match ($avatar->mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($avatar->tmpName),
            'image/png' => @imagecreatefrompng($avatar->tmpName),
            'image/webp' => @imagecreatefromwebp($avatar->tmpName),
            default => false,
        };
        if (!$source instanceof \GdImage) {
            throw new HttpException('CORRUPTED_AVATAR', 422);
        }
        $source = $this->orient($source, $avatar);
        try {
            return new RenderedAvatarDto(
                full: $this->encode($source, AvatarVariantEnum::FULL->size()),
                thumb: $this->encode($source, AvatarVariantEnum::THUMB->size()),
            );
        } finally {
            imagedestroy($source);
        }
    }

    /** Applies EXIF orientations 3, 6 and 8 of JPEG photos; mirrored orientations are rare for avatars and kept as is. */
    private function orient(\GdImage $image, InspectedAvatarDto $avatar): \GdImage
    {
        if ('image/jpeg' !== $avatar->mimeType || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($avatar->tmpName);
        $angle = match (is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };
        if (0 === $angle) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof \GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    private function encode(\GdImage $source, int $size): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $canvas = imagecreatetruecolor($size, $size);
        if (!$canvas instanceof \GdImage) {
            throw new AccessStorageException('Cannot allocate avatar canvas.');
        }
        $stream = fopen('php://memory', 'w+b');
        try {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, (int)imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            if (!imagecopyresampled($canvas, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side)) {
                throw new AccessStorageException('Cannot scale avatar.');
            }
            if (false === $stream || !imagewebp($canvas, $stream, self::QUALITY) || !rewind($stream)) {
                throw new AccessStorageException('Cannot encode avatar.');
            }
            $content = stream_get_contents($stream);
            if (!is_string($content) || '' === $content) {
                throw new AccessStorageException('Cannot encode avatar.');
            }

            return $content;
        } finally {
            imagedestroy($canvas);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
