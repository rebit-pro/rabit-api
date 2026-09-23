<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

/** Факт покупок детей для правил переноса; сами заказы через этот контракт не меняются. */
interface ChildOrdersInterface
{
    /**
     * Дети, у которых есть хотя бы одна строка заказа в любом статусе оплаты.
     *
     * @param list<int> $childIds внутренние ID детей Media
     *
     * @return array<int, true> по ID ребёнка
     */
    public function withOrders(array $childIds): array;
}
