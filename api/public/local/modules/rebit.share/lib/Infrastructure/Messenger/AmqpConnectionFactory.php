<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Фабрика AMQP-транспортов для Symfony Messenger.
 * Создаёт транспорт из DSN с указанным именем очереди.
 */
final readonly class AmqpConnectionFactory implements MessageTransportFactoryInterface
{
    public function __construct(
        private string $dsn,
    ) {}

    public function create(MessengerQueueEnum $queue): TransportInterface
    {
        if ('' === $this->dsn) {
            throw new \RuntimeException(
                'MESSENGER_TRANSPORT_DSN не задан или пуст. '
                . 'Укажите корректный AMQP DSN в .env или docker-compose.',
            );
        }

        $queueName = $queue->value;

        $amqpConfig = [
            'queues' => [
                $queueName => [
                    'binding_keys' => [$queueName],
                ],
            ],
            'exchange' => [
                'name' => $queueName,
                'type' => 'direct',
                'default_publish_routing_key' => $queueName,
            ],
            'auto_setup' => true,
        ];

        $connection = Connection::fromDsn($this->dsn, $amqpConfig);

        return new AmqpTransport($connection, Serializer::create());
    }
}
