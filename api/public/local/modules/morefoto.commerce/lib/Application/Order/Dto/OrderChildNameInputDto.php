<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class OrderChildNameInputDto
{
    public function __construct(public ?string $firstName, public ?string $lastName) {}
}
