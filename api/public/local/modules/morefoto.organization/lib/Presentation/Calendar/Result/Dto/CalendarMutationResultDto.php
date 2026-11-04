<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Calendar\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class CalendarMutationResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $groupId,
        public int $revision,
        public CalendarPeriodResultDto $calendar,
    ) {}
}
