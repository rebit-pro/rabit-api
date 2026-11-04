<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class CalendarPeriodResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $timezone,
        public ?string $sentAt,
        public ?string $closesAt,
        public ?string $digitalSalesUntil,
        public ?string $deliveryDueAt,
        public string $status,
    ) {}
}
