<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

final readonly class CalendarMutationOutputDto
{
    public function __construct(
        public string $groupId,
        public int $revision,
        public GroupCalendarOutputDto $calendar,
    ) {}
}
