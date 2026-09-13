<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Calendar;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;

final readonly class ServerCalendarClock implements CalendarClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . time()))->setTimezone(new \DateTimeZone('UTC'));
    }
}
