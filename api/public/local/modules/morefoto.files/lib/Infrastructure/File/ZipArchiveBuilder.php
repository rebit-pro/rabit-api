<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\File;

use Morefoto\Files\Application\Files\Contract\ArchiveBuilderInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;

/** Архив купленных оригиналов заказа: имена файлов покупателя, атомарная запись без сжатия — общая `AtomicZipWriter`. */
final readonly class ZipArchiveBuilder implements ArchiveBuilderInterface
{
    public function __construct(private AtomicZipWriter $writer = new AtomicZipWriter()) {}

    public function build(array $files, string $target): int
    {
        $entries = [];
        foreach ($files as $file) {
            $entries[] = ['path' => $file->absolutePath, 'name' => $file->filename];
        }
        try {
            return $this->writer->write($entries, [], $target);
        } catch (\RuntimeException $error) {
            throw new FilesStorageException($error->getMessage(), 0, $error);
        }
    }
}
