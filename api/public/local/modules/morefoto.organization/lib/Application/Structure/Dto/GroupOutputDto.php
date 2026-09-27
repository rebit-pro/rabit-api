<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Morefoto\Organization\Application\Calendar\Service\CalendarProjection;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class GroupOutputDto implements ResponseDtoInterface
{
    public function __construct(public string $id, public string $shootId, public string $shootName, public string $name, public string $groupKind, public int $revision, public ?int $teacherId, public string $status, public string $timezone, public ?string $sentAt, public ?string $closesAt, public ?string $deliveryDueAt) {}

    /** @param array{
     *     UF_PUBLIC_ID: string, UF_NAME: string, UF_KIND: string, UF_REVISION: int|string,
     *     UF_SENT_AT: null|string, UF_CLOSES_AT: null|string, UF_DELIVERY_DUE_AT: null|string, UF_TIMEZONE: string,
     * } $row
     */
    public static function fromRow(array $row, string $shootId, string $shootName, ?int $teacherId, \DateTimeImmutable $now): self
    {
        $calendar = CalendarProjection::create(GroupCalendar::fromStorage(
            $row['UF_SENT_AT'],
            $row['UF_CLOSES_AT'],
            $row['UF_DELIVERY_DUE_AT'],
            $row['UF_TIMEZONE'],
        ), $now);

        return new self(
            id: $row['UF_PUBLIC_ID'],
            shootId: $shootId,
            shootName: $shootName,
            name: $row['UF_NAME'],
            groupKind: $row['UF_KIND'],
            revision: (int)$row['UF_REVISION'],
            teacherId: $teacherId,
            status: $calendar->status,
            timezone: $calendar->timezone,
            sentAt: $calendar->sentAt,
            closesAt: $calendar->closesAt,
            deliveryDueAt: $calendar->deliveryDueAt,
        );
    }
}
