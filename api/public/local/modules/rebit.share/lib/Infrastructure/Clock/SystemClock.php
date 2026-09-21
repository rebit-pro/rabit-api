<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Clock;

use Rebit\Share\Application\Contract\Clock\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
