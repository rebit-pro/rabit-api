<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Order\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderPeriodResultDto implements ResultDtoInterface
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
