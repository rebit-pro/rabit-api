<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class BuyerOrderOutputDto
{
    public function __construct(public OrderOutputDto $order, public OrderPeriodOutputDto $period, public string $accessKeyExpiresAt) {}
}
