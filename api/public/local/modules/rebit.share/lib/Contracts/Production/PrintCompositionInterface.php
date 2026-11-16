<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Production;

use Rebit\Share\Contracts\Production\Dto\PrintCompositionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionsOutputDto;

/** Сохранённый состав печати без чтения чужих таблиц, пересчёта путей и создания нового задания. */
interface PrintCompositionInterface
{
    public const int MAX_LINES = 10000;

    /** Роли без доступа к Production получают allowed=false; ошибки источника не заменяются пустым списком. */
    public function available(int $actorId, ?string $groupId, ?string $institutionId): PrintVersionsOutputDto;

    /** Конкретный публичный ID group/institution; чужая версия, неготовый файл и layout 1 отклоняются явно. */
    public function read(int $actorId, string $type, string $id): PrintCompositionOutputDto;
}
