<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

use Morefoto\Files\Application\Files\Dto\EntitledFileOutputDto;

interface ArchiveBuilderInterface
{
    /**
     * Собирает ZIP без сжатия во временный файл рядом с целью и публикует его атомарным rename.
     *
     * @param non-empty-list<EntitledFileOutputDto> $files
     *
     * @return int размер готового архива
     */
    public function build(array $files, string $target): int;
}
