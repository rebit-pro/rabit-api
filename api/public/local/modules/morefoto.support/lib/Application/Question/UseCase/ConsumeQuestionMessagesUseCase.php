<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\MessageBusInterface;

/** Запускает ограниченный по числу сообщений и времени worker очереди доставки вопросов в MAX. */
final readonly class ConsumeQuestionMessagesUseCase
{
    public function __construct(
        private MessageConsumerRunnerInterface $runner,
        private MessageTransportFactoryInterface $transports,
        private MessageBusInterface $bus,
    ) {}

    public function execute(int $limit, int $timeLimit): void
    {
        $queue = MessengerQueueEnum::SUPPORT_MAX;
        $this->runner->run($this->transports->create($queue), $queue->value, $this->bus, $limit, $timeLimit);
    }
}
