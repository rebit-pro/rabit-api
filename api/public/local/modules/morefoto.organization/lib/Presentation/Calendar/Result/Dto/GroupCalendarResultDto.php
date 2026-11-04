<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class GroupCalendarResultDto implements ResultDtoInterface
{
    /** @param list<CalendarExtensionHistoryResultDto> $history */
    public function __construct(
        public string $groupId,
        public int $revision,
        public CalendarPeriodResultDto $calendar,
        public array $history,
    ) {}
}
