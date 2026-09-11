<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Contract;

interface ClockInterface
{
    public function now(): int;
}
