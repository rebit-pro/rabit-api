<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Service;

use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

final readonly class CalendarProjection
{
    public static function create(GroupCalendar $calendar, \DateTimeImmutable $now): GroupCalendarOutputDto
    {
        $zone = new \DateTimeZone($calendar->timezone);

        return new GroupCalendarOutputDto(
            timezone: $calendar->timezone,
            sentAt: $calendar->sentAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            closesAt: $calendar->closesAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            deliveryDueAt: $calendar->deliveryDueAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            status: $calendar->status($now),
        );
    }
}
