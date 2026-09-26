<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\RemovedMediaFilesDto;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;

/** Кадры, дети и ключи галерей удаляемой структуры. */
interface StructureMediaRemovalInterface
{
    /**
     * Внутри транзакции вызывающего, после Commerce и Handoff: при кадрах в обработке отказывает `409 PHOTO_PROCESSING`.
     * Возвращает файлы для `removeFiles()`.
     */
    public function remove(StructureRemovalDto $removal): RemovedMediaFilesDto;

    /** После commit: оставшийся файл не возвращает кадр, поэтому ошибки только журналируются. */
    public function removeFiles(RemovedMediaFilesDto $files): void;
}
