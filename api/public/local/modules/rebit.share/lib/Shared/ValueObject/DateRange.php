<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\ValueObject;

final class DateRange extends AbstractDateRange
{
    public const string DEFAULT_FORMAT = 'Y-m-d';

    protected function normalize(): void
    {
        $this->start = $this->start->setTime(0, 0);
        $this->end = $this->end->setTime(23, 59, 59);
    }
}
