<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar;

use Morefoto\Organization\Application\Calendar\Dto\CalendarExtensionHistoryOutputDto;
use Morefoto\Organization\Application\Calendar\Dto\GroupCalendarDetailsOutputDto;
use Morefoto\Organization\Presentation\Calendar\Result\Dto\CalendarExtensionHistoryResultDto;
use Morefoto\Organization\Presentation\Calendar\Result\Dto\CalendarMutationResultDto;
use Morefoto\Organization\Presentation\Calendar\Result\Dto\CalendarPeriodResultDto;
use Morefoto\Organization\Presentation\Calendar\Result\Dto\GroupCalendarResultDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;

final readonly class CalendarResultMapper
{
    public function details(GroupCalendarDetailsOutputDto $output): GroupCalendarResultDto
    {
        return new GroupCalendarResultDto(
            $output->groupId,
            $output->revision,
            $this->period($output->calendar),
            array_map($this->history(...), $output->history),
        );
    }

    public function mutation(CalendarMutationOutputDto $output): CalendarMutationResultDto
    {
        return new CalendarMutationResultDto($output->groupId, $output->revision, $this->period($output->calendar));
    }

    private function history(CalendarExtensionHistoryOutputDto $output): CalendarExtensionHistoryResultDto
    {
        return new CalendarExtensionHistoryResultDto(
            $output->actorId,
            $output->actorName,
            $output->at,
            $output->days,
            $this->period($output->before),
            $this->period($output->after),
        );
    }

    private function period(GroupCalendarOutputDto $output): CalendarPeriodResultDto
    {
        return new CalendarPeriodResultDto(
            $output->timezone,
            $output->sentAt,
            $output->closesAt,
            $output->digitalSalesUntil,
            $output->deliveryDueAt,
            $output->status,
        );
    }
}
