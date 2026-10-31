<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

final readonly class OrderChildNameRequestDto
{
    public function __construct(public ?string $firstName = null, public ?string $lastName = null) {}
}
