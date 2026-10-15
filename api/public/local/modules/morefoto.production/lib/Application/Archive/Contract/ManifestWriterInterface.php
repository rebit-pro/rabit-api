<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\Contract;

use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;

interface ManifestWriterInterface
{
    /** Имя файла состава в корне архива. */
    public const string FILENAME = 'состав.csv';

    /**
     * CSV состава для лаборатории и сотрудника: одна строка на строку заказа, безопасный для табличных редакторов.
     *
     * @param list<ArchiveLine>     $lines
     * @param array<string, string> $extensions расширение файла по публичному ID кадра
     */
    public function csv(array $lines, array $extensions): string;
}
