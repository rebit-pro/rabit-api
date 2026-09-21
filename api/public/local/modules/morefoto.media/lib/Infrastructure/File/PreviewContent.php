<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\File;

use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class PreviewContent implements PreviewContentInterface
{
    public function __construct(private string $root) {}

    public function read(string $photoId, string $variant): PreviewContentOutputDto
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $photoId) || !in_array($variant, ['thumb', 'preview'], true)) {
            throw new HttpException('PHOTO_NOT_FOUND', 404);
        }
        $path = rtrim($this->root, '/') . '/' . substr($photoId, 0, 2) . '/' . $photoId . '-' . $variant . '.webp';
        $size = is_file($path) ? filesize($path) : false;
        if (false === $size || 0 === $size || 5242880 < $size) {
            throw new HttpException('PREVIEW_UNAVAILABLE', 404);
        }
        $content = file_get_contents($path);
        if (false === $content) {
            throw new HttpException('PREVIEW_UNAVAILABLE', 404);
        }

        return new PreviewContentOutputDto($content);
    }
}
