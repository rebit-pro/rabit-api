<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class PlacedOrderOutputDto
{
    public function __construct(public int $orderId, public CreatedOrderOutputDto $created) {}
}
