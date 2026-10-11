<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Service;

use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

/**
 * Переводит календарь группы в межмодульный контракт: моменты передачи ссылки, окончания приёма печати, продажи
 * электронных фото и передачи печати — в зоне группы, состояние приёма — на заданный момент сервера. Одна проекция
 * для ответов Organization, справочника групп и журнала, чтобы сроки и состояние нигде не расходились.
 */
final readonly class CalendarProjection
{
    public static function create(GroupCalendar $calendar, \DateTimeImmutable $now): GroupCalendarOutputDto
    {
        $zone = new \DateTimeZone($calendar->timezone);

        return new GroupCalendarOutputDto(
            timezone: $calendar->timezone,
            sentAt: $calendar->sentAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            closesAt: $calendar->closesAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            digitalSalesUntil: $calendar->digitalSalesUntil?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            deliveryDueAt: $calendar->deliveryDueAt?->setTimezone($zone)->format(\DateTimeInterface::ATOM),
            status: $calendar->status($now),
        );
    }
}
