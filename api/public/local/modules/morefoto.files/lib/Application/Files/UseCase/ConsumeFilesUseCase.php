<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\MessageBusInterface;

/** Запускает фоновую сборку архивов купленных файлов из очереди filesArchive с ограничением по числу сообщений и времени. */
final readonly class ConsumeFilesUseCase
{
    public function __construct(
        private MessageConsumerRunnerInterface $runner,
        private MessageTransportFactoryInterface $transports,
        private MessageBusInterface $bus,
    ) {}

    public function execute(int $limit, int $timeLimit): void
    {
        $queue = MessengerQueueEnum::FILES_ARCHIVE;
        $this->runner->run(
            transport: $this->transports->create($queue),
            queueName: $queue->value,
            bus: $this->bus,
            limit: $limit,
            timeLimit: $timeLimit,
        );
    }
}
