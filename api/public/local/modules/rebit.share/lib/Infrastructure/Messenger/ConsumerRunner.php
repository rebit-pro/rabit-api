<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Универсальный runner для Symfony Messenger Worker.
 *
 * Инкапсулирует инфраструктурную логику: event dispatcher, retry, failed transport.
 * Доменные runner'ы делегируют сюда, оставляя у себя только маппинг enum → transport/queue.
 *
 * Защита от параллельного запуска — ответственность вызывающей команды (атрибут #[WithLock]).
 */
final readonly class ConsumerRunner implements ConsumerRunnerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private ?RetryStrategyInterface $retryStrategy = null,
        private ?TransportInterface $failedTransport = null,
    ) {}

    public function run(
        TransportInterface $transport,
        string $queueName,
        MessageBusInterface $bus,
        int $limit,
        int $timeLimit,
    ): void {
        $eventDispatcher = $this->createEventDispatcher($transport, $queueName, $limit, $timeLimit);

        $worker = new Worker(
            receivers: [$queueName => $transport],
            bus: $bus,
            eventDispatcher: $eventDispatcher,
            logger: $this->logger,
        );

        $worker->run();
    }

    private function createEventDispatcher(
        TransportInterface $transport,
        string $queueName,
        int $limit,
        int $timeLimit,
    ): EventDispatcher {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
        $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));

        if (null !== $this->retryStrategy) {
            $eventDispatcher->addSubscriber(new SendFailedMessageForRetryListener(
                new SimpleServiceContainer([$queueName => $transport]),
                new SimpleServiceContainer([$queueName => $this->retryStrategy]),
                $this->logger,
                $eventDispatcher,
            ));
        }

        if (null !== $this->failedTransport) {
            $eventDispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
                new SimpleServiceContainer([$queueName => $this->failedTransport]),
                $this->logger,
            ));
        }

        return $eventDispatcher;
    }
}
