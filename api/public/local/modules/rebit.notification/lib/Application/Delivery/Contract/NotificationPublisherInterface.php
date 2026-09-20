<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\Contract;

interface NotificationPublisherInterface
{
    public function publish(string $operationId): void;
}
