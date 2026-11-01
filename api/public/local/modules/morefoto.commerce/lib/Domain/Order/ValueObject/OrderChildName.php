<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

final readonly class OrderChildName
{
    public function __construct(public string $firstName, public string $lastName) {}
}
