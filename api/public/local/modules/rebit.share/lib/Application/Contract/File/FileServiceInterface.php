<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\File;

/**
 * Интерфейс возвращает список ссылок на файлы по их ID.
 *
 * @todo Реализовать адаптер и зарегистрировать в DI.
 *       Контракт объявлен, но не имеет реализации ни в одном модуле.
 */
interface FileServiceInterface
{
    /**
     * @param int[] $fileIds
     *
     * @return array<int, string> // ключ - ID файла
     */
    public function getListByIds(array $fileIds): array;
}
