<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Dto;

final readonly class ArchiveFileInputDto
{
    /**
     * @param string $path абсолютный путь оригинала
     * @param string $name путь внутри архива
     */
    public function __construct(
        public string $path,
        public string $name,
    ) {}
}
