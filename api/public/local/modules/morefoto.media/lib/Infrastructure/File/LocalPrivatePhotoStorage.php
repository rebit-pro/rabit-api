<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\File;

use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Dto\InspectedPhoto;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;

final readonly class LocalPrivatePhotoStorage implements PrivatePhotoStorageInterface
{
    public function __construct(private string $root) {}

    public function path(string $shootId, InspectedPhoto $photo): string
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $shootId)) {
            throw new MediaStorageException('Invalid private media scope.');
        }
        $extension = match ($photo->mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new MediaStorageException('Unsupported private media type.'),
        };

        return $shootId . '/' . substr($photo->fingerprint, 0, 2) . '/' . $photo->fingerprint . '.' . $extension;
    }

    public function store(string $shootId, InspectedPhoto $photo): string
    {
        $relative = $this->path($shootId, $photo);
        $target = $this->absolutePath($relative);
        if (is_file($target)) {
            return $relative;
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new MediaStorageException('Cannot create private media directory.');
        }
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (!copy($photo->tmpName, $temporary) || !chmod($temporary, 0600)) {
                throw new MediaStorageException('Cannot persist private original.');
            }
            $hash = hash_file('sha256', $temporary);
            if (!is_string($hash) || !hash_equals($photo->fingerprint, $hash)) {
                throw new MediaStorageException('Private original verification failed.');
            }
            if (!rename($temporary, $target) && !is_file($target)) {
                throw new MediaStorageException('Cannot publish private original.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $relative;
    }

    public function absolutePath(string $relativePath): string
    {
        if ('' === $relativePath || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new MediaStorageException('Invalid private media path.');
        }

        return rtrim($this->root, '/') . '/' . $relativePath;
    }

    public function delete(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);
        if (is_file($path) && !unlink($path)) {
            throw new MediaStorageException('Cannot delete unused private original.');
        }
    }
}
