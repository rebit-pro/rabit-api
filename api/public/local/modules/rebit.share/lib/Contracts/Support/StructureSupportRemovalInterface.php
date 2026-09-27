<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Support;

use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;

/** Вопросы родителей по удаляемым группам. */
interface StructureSupportRemovalInterface
{
    /** Внутри транзакции вызывающего удаляет вопросы родителей по группам вместе с перепиской. */
    public function remove(StructureRemovalDto $removal): void;
}
