<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\File;

use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/** Архивы лежат в приватном каталоге; nginx отдаёт их и оригиналы Media только через internal-location /_protected/. */
final readonly class LocalProtectedStorage implements ProtectedStorageInterface
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D';
    private const string ARCHIVE_URI = '/_protected/files/';
    private const string ORIGINAL_URI = '/_protected/media/';

    public function __construct(private string $root) {}

    public function archivePath(string $orderPublicId, string $downloadId): string
    {
        if (1 !== preg_match(self::UUID, $orderPublicId) || 1 !== preg_match(self::UUID, $downloadId)) {
            throw new FilesStorageException('Invalid archive scope.');
        }

        return 'archives/' . $orderPublicId . '/' . $downloadId . '.zip';
    }

    public function absoluteArchivePath(string $relativePath): string
    {
        return rtrim($this->root, '/') . '/' . $this->safe($relativePath);
    }

    public function deleteArchive(string $relativePath): void
    {
        $path = $this->absoluteArchivePath($relativePath);
        // ZipArchiveBuilder writes `<archive>.<random>.tmp` next to the target; a killed worker may leave one behind.
        foreach ([$path, ...(glob($path . '.*.tmp') ?: [])] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new FilesStorageException('Cannot delete archive.');
            }
        }
        $directory = dirname($path);
        if (is_dir($directory) && [] === array_diff(scandir($directory) ?: [], ['.', '..'])) {
            @rmdir($directory);
        }
    }

    public function archiveUri(string $relativePath): string
    {
        return self::ARCHIVE_URI . $this->safe($relativePath);
    }

    public function originalUri(string $mediaRelativePath): string
    {
        return self::ORIGINAL_URI . $this->safe($mediaRelativePath);
    }

    private function safe(string $relativePath): string
    {
        if (1 !== preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)*$~D', $relativePath) || str_contains($relativePath, '..')) {
            throw new FilesStorageException('Invalid protected file path.');
        }

        return $relativePath;
    }
}
