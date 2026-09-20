<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Contract;

interface NotificationClockInterface
{
    public function now(): \DateTimeImmutable;
}
