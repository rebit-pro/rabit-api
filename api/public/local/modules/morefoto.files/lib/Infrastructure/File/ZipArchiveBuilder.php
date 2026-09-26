<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\File;

use Morefoto\Files\Application\Files\Contract\ArchiveBuilderInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/** JPEG уже сжат, поэтому архив собирается без сжатия: быстрее и без лишней нагрузки на CPU.
 * Воркер штатно работает от www-data (APP_RUN_AS_USER); если его запустили от root, созданные каталоги и файл всё равно
 * наследуют владельца корня хранилища, чтобы nginx (uid 1000) мог их отдать.
 */
final readonly class ZipArchiveBuilder implements ArchiveBuilderInterface
{
    public function build(array $files, string $target): int
    {
        $directory = dirname($target);
        $created = [];
        for ($anchor = $directory; !is_dir($anchor) && dirname($anchor) !== $anchor; $anchor = dirname($anchor)) {
            array_unshift($created, $anchor);
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new FilesStorageException('Cannot create archive directory.');
        }
        foreach ($created as $path) {
            $this->inheritOwner($path, $anchor);
        }
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $zip = new \ZipArchive();
        $open = false;
        try {
            if (true !== $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::EXCL)) {
                throw new FilesStorageException('Cannot open archive.');
            }
            $open = true;
            foreach ($files as $file) {
                if (!is_file($file->absolutePath) || !$zip->addFile($file->absolutePath, $file->filename)
                    || !$zip->setCompressionName($file->filename, \ZipArchive::CM_STORE)) {
                    throw new FilesStorageException('Cannot add original to archive.');
                }
            }
            $open = false;
            if (!$zip->close()) {
                throw new FilesStorageException('Cannot write archive.');
            }
            clearstatcache(true, $temporary);
            $bytes = filesize($temporary);
            if (false === $bytes || 0 === $bytes || !chmod($temporary, 0600)) {
                throw new FilesStorageException('Cannot publish archive.');
            }
            $this->inheritOwner($temporary, $anchor);
            if (!rename($temporary, $target)) {
                throw new FilesStorageException('Cannot publish archive.');
            }

            return $bytes;
        } finally {
            if ($open) {
                // Otherwise the ZipArchive destructor would still write the partial archive to disk.
                $zip->unchangeAll();
                $zip->close();
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function inheritOwner(string $path, string $anchor): void
    {
        if (!function_exists('posix_geteuid') || 0 !== posix_geteuid()) {
            return;
        }
        $owner = stat($anchor);
        if (false === $owner || !chown($path, $owner['uid']) || !chgrp($path, $owner['gid'])) {
            throw new FilesStorageException('Cannot hand the archive over to the storage owner.');
        }
    }
}
