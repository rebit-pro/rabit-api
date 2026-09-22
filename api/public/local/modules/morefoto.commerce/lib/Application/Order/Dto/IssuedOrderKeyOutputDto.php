<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class IssuedOrderKeyOutputDto
{
    public function __construct(public string $key, public string $expiresAt) {}
}
