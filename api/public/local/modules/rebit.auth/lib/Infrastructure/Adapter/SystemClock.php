<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Rebit\Auth\Application\Auth\Contract\ClockInterface;

final readonly class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
