<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit\Double;

use Rebit\Share\Application\Contract\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(public \DateTimeImmutable $now = new \DateTimeImmutable('2026-09-25T12:00:00+00:00')) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
