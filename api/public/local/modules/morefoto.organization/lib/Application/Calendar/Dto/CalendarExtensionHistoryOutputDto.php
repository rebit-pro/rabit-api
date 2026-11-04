<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Dto;

use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

final readonly class CalendarExtensionHistoryOutputDto
{
    public function __construct(
        public int $actorId,
        public string $actorName,
        public string $at,
        public ?int $days,
        public GroupCalendarOutputDto $before,
        public GroupCalendarOutputDto $after,
    ) {}
}
