<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Contract;

interface CalendarClockInterface
{
    public function now(): \DateTimeImmutable;
}
