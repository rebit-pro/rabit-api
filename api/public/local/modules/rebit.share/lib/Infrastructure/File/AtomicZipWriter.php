<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\File;

/**
 * Пишет ZIP из приватных файлов во временный файл рядом с целью и публикует его атомарным rename: читатель видит
 * либо готовый архив, либо ничего. JPEG уже сжат, поэтому записи хранятся без сжатия — быстрее и без нагрузки на CPU.
 * Воркер штатно работает от www-data; если его запустили от root, созданные каталоги и файл наследуют владельца
 * ближайшего существующего каталога хранилища, чтобы nginx (uid 1000) мог их отдать.
 */
final readonly class AtomicZipWriter
{
    /**
     * @param list<array{path: string, name: string}> $files файлы с диска и их имена внутри архива
     * @param array<string, string>                   $texts текстовые записи: имя внутри архива => содержимое
     *
     * @return int размер опубликованного архива в байтах
     *
     * @throws \RuntimeException архив не записан; временный файл удалён
     */
    public function write(array $files, array $texts, string $target): int
    {
        $directory = dirname($target);
        $created = [];
        for ($anchor = $directory; !is_dir($anchor) && dirname($anchor) !== $anchor; $anchor = dirname($anchor)) {
            array_unshift($created, $anchor);
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create archive directory.');
        }
        foreach ($created as $path) {
            $this->inheritOwner($path, $anchor);
        }
        $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $zip = new \ZipArchive();
        $open = false;
        try {
            if (true !== $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::EXCL)) {
                throw new \RuntimeException('Cannot open archive.');
            }
            $open = true;
            foreach ($files as $file) {
                if (!is_file($file['path']) || !$zip->addFile($file['path'], $file['name'])
                    || !$zip->setCompressionName($file['name'], \ZipArchive::CM_STORE)) {
                    throw new \RuntimeException('Cannot add a file to the archive.');
                }
            }
            foreach ($texts as $name => $content) {
                if (!$zip->addFromString($name, $content)) {
                    throw new \RuntimeException('Cannot add a text entry to the archive.');
                }
            }
            $open = false;
            if (!$zip->close()) {
                throw new \RuntimeException('Cannot write archive.');
            }
            clearstatcache(true, $temporary);
            $bytes = filesize($temporary);
            if (false === $bytes || 0 === $bytes || !chmod($temporary, 0600)) {
                throw new \RuntimeException('Cannot publish archive.');
            }
            $this->inheritOwner($temporary, $anchor);
            if (!rename($temporary, $target)) {
                throw new \RuntimeException('Cannot publish archive.');
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
            throw new \RuntimeException('Cannot hand the archive over to the storage owner.');
        }
    }
}
