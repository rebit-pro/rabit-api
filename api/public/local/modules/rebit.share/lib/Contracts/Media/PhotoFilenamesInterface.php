<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

/** Исходные имена кадров для фиксации производственного состава, независимо от доступности оригиналов. */
interface PhotoFilenamesInterface
{
    /**
     * Доступ к группе проверяет потребитель. Неизвестные ID пропускаются; имя не вычисляется из кода или пути хранения.
     *
     * @param list<string> $photoIds публичные ID кадров
     *
     * @return array<string, string> исходное имя по публичному ID, включая неготовые кадры и кадры без оригинала
     */
    public function filenames(array $photoIds): array;
}
