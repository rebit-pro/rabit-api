<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class CreatedOrderOutputDto
{
    public function __construct(public OrderOutputDto $order, public string $accessKey, public string $accessKeyExpiresAt) {}
}
