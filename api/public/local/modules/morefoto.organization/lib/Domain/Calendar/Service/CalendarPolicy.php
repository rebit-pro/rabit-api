<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Calendar\Service;

final readonly class CalendarPolicy
{
    public const string TIMEZONE = 'Europe/Moscow';

    public static function addDays(\DateTimeImmutable $instant, int $days, string $timezone = self::TIMEZONE): \DateTimeImmutable
    {
        if (0 > $days) {
            throw new \InvalidArgumentException('Calendar days cannot be negative.');
        }

        return $instant->setTimezone(new \DateTimeZone($timezone))->add(new \DateInterval('P' . $days . 'D'));
    }

    public static function addMonthClamped(\DateTimeImmutable $instant, string $timezone = self::TIMEZONE): \DateTimeImmutable
    {
        $local = $instant->setTimezone(new \DateTimeZone($timezone));
        $nextMonth = $local->setDate((int)$local->format('Y'), (int)$local->format('n'), 1)->add(new \DateInterval('P1M'));

        return $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('n'), min((int)$local->format('j'), (int)$nextMonth->format('t')));
    }
}
