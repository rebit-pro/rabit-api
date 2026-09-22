<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Transfer;

use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;

final readonly class ChildOrders implements ChildOrdersInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function withOrders(array $childIds): array
    {
        $ids = array_values(array_unique(array_filter($childIds, static fn(int $id): bool => 0 < $id)));

        return [] === $ids ? [] : array_fill_keys($this->orders->childrenWithOrders($ids), true);
    }
}
