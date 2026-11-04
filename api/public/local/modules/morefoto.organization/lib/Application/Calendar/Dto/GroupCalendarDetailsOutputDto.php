<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Dto;

use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

final readonly class GroupCalendarDetailsOutputDto
{
    /** @param list<CalendarExtensionHistoryOutputDto> $history */
    public function __construct(
        public string $groupId,
        public int $revision,
        public GroupCalendarOutputDto $calendar,
        public array $history,
    ) {}
}
