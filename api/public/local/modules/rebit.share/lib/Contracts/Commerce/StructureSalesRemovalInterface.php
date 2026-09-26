<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;

/** Продажи удаляемой структуры: заказы запрещают удаление, остальное Commerce убирает сам. */
interface StructureSalesRemovalInterface
{
    /**
     * Внутри транзакции вызывающего: при заказах отказывает `409 STRUCTURE_HAS_ORDERS`, иначе удаляет условия продажи
     * групп, корзины и незавершённые оформления их галерей.
     */
    public function remove(StructureRemovalDto $removal): void;
}
