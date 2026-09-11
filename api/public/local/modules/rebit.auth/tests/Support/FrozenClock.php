<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Support;

use Rebit\Auth\Application\Auth\Contract\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private int $timestamp = 1800000000) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}
