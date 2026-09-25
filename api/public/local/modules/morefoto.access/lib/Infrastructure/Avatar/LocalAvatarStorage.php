<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Avatar;

use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

/** Private avatar files `<root>/<userId>/<version>-<size>.webp`; served only through the API, never by nginx. */
final readonly class LocalAvatarStorage implements AvatarStorageInterface
{
    public function __construct(
        private string $root,
    ) {}

    public function write(int $userId, int $version, RenderedAvatarDto $avatar): void
    {
        $directory = $this->directory($userId);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new AccessStorageException('Cannot create avatar directory.');
        }
        foreach ([[AvatarVariantEnum::FULL, $avatar->full], [AvatarVariantEnum::THUMB, $avatar->thumb]] as [$variant, $content]) {
            $target = $this->path($userId, $version, $variant);
            $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';
            try {
                if (false === file_put_contents($temporary, $content) || !chmod($temporary, 0600) || !rename($temporary, $target)) {
                    throw new AccessStorageException('Cannot write avatar.');
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }
    }

    public function read(int $userId, int $version, AvatarVariantEnum $variant): ?string
    {
        $path = $this->path($userId, $version, $variant);
        $content = is_file($path) ? file_get_contents($path) : false;

        return false === $content ? null : $content;
    }

    public function prune(int $userId, ?int $keepVersion): void
    {
        $directory = $this->directory($userId);
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $name) {
            if (1 !== preg_match('/^(\d+)-(?:64|256)\.webp$/D', $name, $match) || (int)$match[1] === $keepVersion) {
                continue;
            }
            if (!unlink($directory . '/' . $name) && is_file($directory . '/' . $name)) {
                throw new AccessStorageException('Cannot delete a previous avatar version.');
            }
        }
        if (null === $keepVersion) {
            @rmdir($directory);
        }
    }

    private function path(int $userId, int $version, AvatarVariantEnum $variant): string
    {
        if (1 > $version) {
            throw new AccessStorageException('Invalid avatar version.');
        }

        return $this->directory($userId) . '/' . $version . '-' . $variant->value . '.webp';
    }

    private function directory(int $userId): string
    {
        if (1 > $userId) {
            throw new AccessStorageException('Invalid avatar owner.');
        }

        return rtrim($this->root, '/') . '/' . $userId;
    }
}
