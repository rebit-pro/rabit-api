<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;

/** Метаданные приватных оригиналов готовых кадров и наличие файлов; право покупателя проверяет вызывающий модуль. */
interface OriginalFilesInterface
{
    /**
     * Только кадры в статусе ready с записью оригинала; неизвестные и неготовые ID пропускаются.
     * Метаданные исчезнувшего файла сохраняются с available=false. Флаг отражает наличие в момент запроса.
     * Перед чтением байтов вызывающий модуль проверяет сам файл.
     *
     * @param list<string> $photoIds публичные ID кадров
     *
     * @return array<string, OriginalFileOutputDto> ключ — публичный ID кадра
     */
    public function originals(array $photoIds): array;
}
