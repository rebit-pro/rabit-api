<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\File;

use Morefoto\Files\Application\Files\Contract\ArchiveBuilderInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/** JPEG уже сжат, поэтому архив собирается без сжатия: быстрее и без лишней нагрузки на CPU. */
final readonly class ZipArchiveBuilder implements ArchiveBuilderInterface
{
    public function build(array $files, string $target): int
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new FilesStorageException('Cannot create archive directory.');
        }
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $zip = new \ZipArchive();
        try {
            if (true !== $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::EXCL)) {
                throw new FilesStorageException('Cannot open archive.');
            }
            foreach ($files as $file) {
                if (!is_file($file->absolutePath) || !$zip->addFile($file->absolutePath, $file->filename)
                    || !$zip->setCompressionName($file->filename, \ZipArchive::CM_STORE)) {
                    throw new FilesStorageException('Cannot add original to archive.');
                }
            }
            if (!$zip->close()) {
                throw new FilesStorageException('Cannot write archive.');
            }
            clearstatcache(true, $temporary);
            $bytes = filesize($temporary);
            if (false === $bytes || 0 === $bytes || !chmod($temporary, 0600) || !rename($temporary, $target)) {
                throw new FilesStorageException('Cannot publish archive.');
            }

            return $bytes;
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
