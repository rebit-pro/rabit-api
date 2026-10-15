<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

use Morefoto\Production\Application\Archive\Dto\ArchiveFileInputDto;

interface ArchiveWriterInterface
{
    /**
     * Атомарно пишет ZIP: оригиналы без сжатия и состав CSV в корне.
     *
     * @param list<ArchiveFileInputDto> $files
     *
     * @return int размер архива в байтах
     */
    public function write(array $files, string $manifest, string $target): int;
}
