<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Dto\OrderPeriodOutputDto;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;

/** Показывает покупателю и сотруднику актуальные сроки группы заказа из календаря Organization.
 * Сроки не копируются в заказ и не выдумываются: история продлений появится вместе с её владельцем.
 */
final readonly class OrderPeriods
{
    public function __construct(private GroupCalendarInterface $calendars, private ClockInterface $clock, private OrderCalendarPolicy $calendar) {}

    public function period(string $groupId): OrderPeriodOutputDto
    {
        $calendar = $this->calendars->get($groupId)->calendar;

        return new OrderPeriodOutputDto(
            groupId: $groupId,
            state: $calendar->status,
            timezone: $calendar->timezone,
            sentAt: $calendar->sentAt,
            closesAt: $calendar->closesAt,
            deliveryDueAt: $calendar->deliveryDueAt,
            now: $this->calendar->display($this->clock->now()),
        );
    }
}
