<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Mapper;

use Morefoto\Organization\Application\Calendar\Dto\CalendarExtensionHistoryOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

/** Восстанавливает снимки календаря из журнала продлений без повторного расчёта исторических сроков. */
final readonly class CalendarExtensionHistoryMapper
{
    /**
     * @param array{UF_ACTOR_ID: int|string, ACTOR_NAME: string, UF_OCCURRED_AT: string, UF_DELTA: string} $row
     */
    public function fromRow(array $row): CalendarExtensionHistoryOutputDto
    {
        /** @var array{
         *     days?: ?int,
         *     before: array{timezone: string, sentAt: ?string, closesAt: ?string, digitalSalesUntil: ?string, deliveryDueAt: ?string, status: string},
         *     after: array{timezone: string, sentAt: ?string, closesAt: ?string, digitalSalesUntil: ?string, deliveryDueAt: ?string, status: string},
         * } $delta */
        $delta = json_decode($row['UF_DELTA'], true, 512, JSON_THROW_ON_ERROR);
        $at = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $row['UF_OCCURRED_AT'], new \DateTimeZone('UTC'));
        if (false === $at) {
            throw new \UnexpectedValueException('Invalid calendar history timestamp.');
        }

        return new CalendarExtensionHistoryOutputDto(
            actorId: (int)$row['UF_ACTOR_ID'],
            actorName: $row['ACTOR_NAME'],
            at: $at->format(\DateTimeInterface::ATOM),
            days: $delta['days'] ?? null,
            before: $this->calendar($delta['before']),
            after: $this->calendar($delta['after']),
        );
    }

    /** @param array{timezone: string, sentAt: ?string, closesAt: ?string, digitalSalesUntil: ?string, deliveryDueAt: ?string, status: string} $value */
    private function calendar(array $value): GroupCalendarOutputDto
    {
        return new GroupCalendarOutputDto(
            timezone: $value['timezone'],
            sentAt: $value['sentAt'],
            closesAt: $value['closesAt'],
            digitalSalesUntil: $value['digitalSalesUntil'],
            deliveryDueAt: $value['deliveryDueAt'],
            status: $value['status'],
        );
    }
}
