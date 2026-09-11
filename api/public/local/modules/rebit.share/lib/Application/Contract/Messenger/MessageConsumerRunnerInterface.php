<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Порт запуска consumer worker'а.
 */
interface MessageConsumerRunnerInterface
{
    public function run(
        TransportInterface $transport,
        string $queueName,
        MessageBusInterface $bus,
        int $limit,
        int $timeLimit,
    ): void;
}
