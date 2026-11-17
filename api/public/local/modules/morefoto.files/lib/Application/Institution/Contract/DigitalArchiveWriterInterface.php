<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Institution\Contract;

use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;

interface DigitalArchiveWriterInterface
{
    /** @param list<array{path: string, name: string, bytes: int}> $files */
    public function build(array $files, string $manifest, string $target): int;

    public function manifest(DigitalArchive $archive): string;

    public function exists(string $path, int $bytes): bool;
}
