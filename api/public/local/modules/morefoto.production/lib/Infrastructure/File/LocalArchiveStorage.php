<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\File;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;

/** Архивы печати лежат в подкаталоге `production/` приватного хранилища файлов; nginx отдаёт их только через /_protected/files/. */
final readonly class LocalArchiveStorage implements ArchiveStorageInterface
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D';
    private const string URI = '/_protected/files/';

    public function __construct(private string $root) {}

    public function archivePath(string $groupPublicId, string $versionPublicId): string
    {
        if (1 !== preg_match(self::UUID, $groupPublicId) || 1 !== preg_match(self::UUID, $versionPublicId)) {
            throw new ProductionStorageException('Invalid print archive scope.');
        }

        return 'production/' . $groupPublicId . '/' . $versionPublicId . '.zip';
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim($this->root, '/') . '/' . $this->safe($relativePath);
    }

    public function uri(string $relativePath): string
    {
        return self::URI . $this->safe($relativePath);
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($relativePath));
    }

    public function delete(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);
        // AtomicZipWriter writes `<archive>.<random>.tmp` next to the target; a killed build may leave one behind.
        foreach ([$path, ...(glob($path . '.*.tmp') ?: [])] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new ProductionStorageException('Cannot delete print archive.');
            }
        }
        $directory = dirname($path);
        if (is_dir($directory) && [] === array_diff(scandir($directory) ?: [], ['.', '..'])) {
            @rmdir($directory);
        }
    }

    private function safe(string $relativePath): string
    {
        if (1 !== preg_match('~^production/[a-f0-9-]{36}/[a-f0-9-]{36}\.zip$~D', $relativePath)) {
            throw new ProductionStorageException('Invalid print archive path.');
        }

        return $relativePath;
    }
}
