<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;

/** Приватные оригиналы готовых кадров для выдачи купленных файлов; право покупателя проверяет вызывающий модуль. */
interface OriginalFilesInterface
{
    /**
     * Только кадры в статусе ready с сохранённым оригиналом; неизвестные и неготовые ID пропускаются.
     *
     * @param list<string> $photoIds публичные ID кадров
     *
     * @return array<string, OriginalFileOutputDto> ключ — публичный ID кадра
     */
    public function originals(array $photoIds): array;
}
