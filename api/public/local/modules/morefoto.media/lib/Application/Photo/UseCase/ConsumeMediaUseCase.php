<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\UseCase;

use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConsumeMediaUseCase
{
    public function __construct(
        private MessageConsumerRunnerInterface $runner,
        private MessageTransportFactoryInterface $transports,
        private MessageBusInterface $bus,
    ) {}

    public function execute(int $limit, int $timeLimit): void
    {
        $queue = MessengerQueueEnum::MEDIA_PROCESSING;
        $this->runner->run(
            transport: $this->transports->create($queue),
            queueName: $queue->value,
            bus: $this->bus,
            limit: $limit,
            timeLimit: $timeLimit,
        );
    }
}
