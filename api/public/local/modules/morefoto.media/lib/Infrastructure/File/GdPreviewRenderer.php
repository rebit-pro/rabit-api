<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\File;

use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Dto\PreviewOutputDto;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;

final readonly class GdPreviewRenderer implements PreviewRendererInterface
{
    public function __construct(
        private string $publicRoot,
        private string $publicUrl,
    ) {}

    public function render(string $originalPath, string $mimeType, string $photoId): PreviewOutputDto
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp') || !is_file($originalPath)) {
            throw new MediaStorageException('Image renderer is unavailable.');
        }
        $started = hrtime(true);
        $source = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($originalPath),
            'image/png' => imagecreatefrompng($originalPath),
            'image/webp' => imagecreatefromwebp($originalPath),
            default => false,
        };
        if (!$source instanceof \GdImage) {
            throw new MediaStorageException('Cannot decode private original.');
        }
        $decoded = hrtime(true);
        try {
            $thumb = $this->writeVariant($source, $photoId, 'thumb', 320);
            $thumbDone = hrtime(true);
            $preview = $this->writeVariant($source, $photoId, 'preview', 1200);
            $previewDone = hrtime(true);
        } finally {
            imagedestroy($source);
        }

        return new PreviewOutputDto(
            thumbSrc: rtrim($this->publicUrl, '/') . '/' . $thumb,
            previewSrc: rtrim($this->publicUrl, '/') . '/' . $preview,
            decodeMs: self::milliseconds($started, $decoded),
            thumbMs: self::milliseconds($decoded, $thumbDone),
            previewMs: self::milliseconds($thumbDone, $previewDone),
        );
    }

    public function remove(string $photoId): void
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $photoId)) {
            throw new MediaStorageException('Invalid preview photo ID.');
        }
        $directory = rtrim($this->publicRoot, '/') . '/' . substr($photoId, 0, 2);
        foreach (['thumb', 'preview'] as $variant) {
            $path = $directory . '/' . $photoId . '-' . $variant . '.webp';
            if (is_file($path) && !unlink($path)) {
                throw new MediaStorageException('Cannot delete protected preview.');
            }
        }
    }

    private static function milliseconds(float|int $from, float|int $to): int
    {
        return (int)(($to - $from) / 1_000_000);
    }

    private function writeVariant(\GdImage $source, string $photoId, string $variant, int $longest): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1.0, $longest / max($sourceWidth, $sourceHeight));
        $width = max(1, (int)round($sourceWidth * $scale));
        $height = max(1, (int)round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        if (!$canvas instanceof \GdImage) {
            throw new MediaStorageException('Cannot allocate preview canvas.');
        }
        try {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $background);
            if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
                throw new MediaStorageException('Cannot scale preview.');
            }
            $this->watermark($canvas, $width, $height);
            $directory = rtrim($this->publicRoot, '/') . '/' . substr($photoId, 0, 2);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new MediaStorageException('Cannot create preview directory.');
            }
            $filename = $photoId . '-' . $variant . '.webp';
            $target = $directory . '/' . $filename;
            $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
            try {
                if (!imagewebp($canvas, $temporary, 80) || !chmod($temporary, 0644) || !rename($temporary, $target)) {
                    throw new MediaStorageException('Cannot write protected preview.');
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            imagedestroy($canvas);
        }

        return substr($photoId, 0, 2) . '/' . $filename;
    }

    private function watermark(\GdImage $image, int $width, int $height): void
    {
        $white = imagecolorallocatealpha($image, 255, 255, 255, 58);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 82);
        $text = 'MoreFoto PREVIEW';
        $font = max(2, min(5, (int)floor($width / 240)));
        $textWidth = imagefontwidth($font) * strlen($text);
        $stepX = max($textWidth + 50, 180);
        $stepY = max(imagefontheight($font) + 70, 100);
        $startY = min(24, max(0, $height - imagefontheight($font)));
        for ($y = $startY; $y < $height; $y += $stepY) {
            for ($x = -($y % $stepX); $x < $width; $x += $stepX) {
                imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
                imagestring($image, $font, $x, $y, $text, $white);
            }
        }
    }
}
