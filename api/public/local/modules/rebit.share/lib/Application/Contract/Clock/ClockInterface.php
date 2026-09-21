<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
