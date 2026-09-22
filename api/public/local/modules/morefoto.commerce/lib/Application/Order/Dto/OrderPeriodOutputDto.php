<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Dto;

final readonly class OrderPeriodOutputDto
{
    public function __construct(
        public string $groupId,
        public string $state,
        public string $timezone,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $deliveryDueAt,
        public string $now,
    ) {}
}
