<?php

declare(strict_types=1);

namespace Rebit\Notification\Infrastructure\Messenger;

use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;
use Rebit\Notification\Application\Delivery\Message\DeliverEmailMessage;
use Rebit\Share\Application\Contract\Messenger\MessagePublisherInterface;

final readonly class NotificationPublisher implements NotificationPublisherInterface
{
    public function __construct(private MessagePublisherInterface $publisher) {}

    public function publish(string $operationId): void
    {
        $this->publisher->dispatch(new DeliverEmailMessage($operationId), 15);
    }
}
