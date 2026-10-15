<?php

declare(strict_types=1);

namespace Morefoto\Production\Infrastructure\File;

use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;

/** Архив печати: оригиналы по папкам продукции и состав CSV в корне, атомарная запись общей `AtomicZipWriter`. */
final readonly class ZipArchiveWriter implements ArchiveWriterInterface
{
    public function __construct(private AtomicZipWriter $writer) {}

    public function write(array $files, string $manifest, string $target): int
    {
        $entries = [];
        foreach ($files as $file) {
            $entries[] = ['path' => $file->path, 'name' => $file->name];
        }
        try {
            return $this->writer->write($entries, [ManifestWriterInterface::FILENAME => $manifest], $target);
        } catch (\RuntimeException $error) {
            throw new ProductionStorageException($error->getMessage(), 0, $error);
        }
    }
}
