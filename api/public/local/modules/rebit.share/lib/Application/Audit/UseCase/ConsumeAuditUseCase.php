<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Audit\UseCase;

use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConsumeAuditUseCase
{
    public function __construct(
        private MessageConsumerRunnerInterface $consumerRunner,
        private MessageTransportFactoryInterface $transportFactory,
        private MessageBusInterface $bus,
    ) {}

    public function execute(int $limit, int $timeLimit): void
    {
        $queue = MessengerQueueEnum::AUDIT;
        $transport = $this->transportFactory->create($queue);

        $this->consumerRunner->run(
            transport: $transport,
            queueName: $queue->value,
            bus: $this->bus,
            limit: $limit,
            timeLimit: $timeLimit,
        );
    }
}
