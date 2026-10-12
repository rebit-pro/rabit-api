<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

/** Факт заказов группы для правила исправления разметки D4; сами заказы через этот контракт не меняются. */
interface GroupOrdersInterface
{
    /**
     * Есть ли у группы хотя бы один заказ в любом статусе оплаты. Вызывающий держит эксклюзивную блокировку строки группы
     * (GroupCalendarInterface::lock): создание заказа читает её разделяемой блокировкой, поэтому ответ не устаревает до
     * конца его транзакции.
     */
    public function exist(string $groupPublicId): bool;
}
