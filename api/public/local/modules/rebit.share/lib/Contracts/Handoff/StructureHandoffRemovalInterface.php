<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Handoff;

use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;

/** Передача родителям удаляемой структуры: ссылки групп и списки сотрудников. */
interface StructureHandoffRemovalInterface
{
    /** Внутри транзакции вызывающего и до удаления детей Media. */
    public function remove(StructureRemovalDto $removal): void;
}
