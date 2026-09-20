<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Time;

use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;

final readonly class ServerNotificationClock implements NotificationClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . time()))->setTimezone(new \DateTimeZone('UTC'));
    }
}
