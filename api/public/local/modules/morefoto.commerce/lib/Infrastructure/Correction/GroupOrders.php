<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Correction;

use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Commerce\GroupOrdersInterface;

final readonly class GroupOrders implements GroupOrdersInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function exist(string $groupPublicId): bool
    {
        return $this->orders->groupHasOrders($groupPublicId);
    }
}
