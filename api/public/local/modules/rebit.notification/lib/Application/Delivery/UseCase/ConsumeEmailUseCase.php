<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Delivery\UseCase;

use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConsumeEmailUseCase
{
    public function __construct(
        private MessageConsumerRunnerInterface $runner,
        private MessageTransportFactoryInterface $transports,
        private MessageBusInterface $bus,
    ) {}

    public function execute(int $limit, int $timeLimit): void
    {
        $queue = MessengerQueueEnum::NOTIFICATION_EMAIL;
        $this->runner->run(
            $this->transports->create($queue),
            $queue->value,
            $this->bus,
            $limit,
            $timeLimit,
        );
    }
}
