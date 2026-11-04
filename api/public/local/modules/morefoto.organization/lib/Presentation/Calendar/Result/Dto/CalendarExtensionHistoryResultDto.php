<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class CalendarExtensionHistoryResultDto implements ResultDtoInterface
{
    public function __construct(
        public int $actorId,
        public string $actorName,
        public string $at,
        public ?int $days,
        public CalendarPeriodResultDto $before,
        public CalendarPeriodResultDto $after,
    ) {}
}
