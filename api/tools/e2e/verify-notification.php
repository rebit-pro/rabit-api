<?php

declare(strict_types=1);

use Psr\Log\NullLogger;
use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;
use Rebit\Notification\Application\Delivery\Dto\DeliveryOperationDto;
use Rebit\Notification\Application\Delivery\Exception\DefiniteDeliveryException;
use Rebit\Notification\Application\Delivery\Message\DeliverEmailMessage;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Infrastructure\Messenger\MessengerBusConfigDto;
use Rebit\Share\Infrastructure\Messenger\MessengerBusFactory;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Symfony\Component\Messenger\Envelope;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\DispatchPendingEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\QueueEmailUseCase;
use Rebit\Notification\Infrastructure\Persistence\DeliveryOperationRepository;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\NotificationDeduplicationConflictException;
use Bitrix\Main\Application;

$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';

$clock = new class implements NotificationClockInterface {
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-20 12:00:00', new DateTimeZone('UTC'));
    }
};
$publisher = new class implements NotificationPublisherInterface {
    public int $published = 0;

    public function publish(string $operationId): void
    {
        ++$this->published;
    }
};
$acceptedTransport = new class implements EmailTransportInterface {
    public function send(DeliveryOperationDto $operation): void {}
};
$rejectedTransport = new class implements EmailTransportInterface {
    public function send(DeliveryOperationDto $operation): void
    {
        throw new DefiniteDeliveryException('test_rejected');
    }
};
$unknownTransport = new class implements EmailTransportInterface {
    public function send(DeliveryOperationDto $operation): void
    {
        throw new RuntimeException('timeout after SMTP handoff');
    }
};
$repository = new DeliveryOperationRepository();
$queue = new QueueEmailUseCase($repository, $publisher, $clock, new NullLogger());
$input = new EmailNotificationInputDto(
    consumer: 'e2e.contract',
    deduplicationKey: 'accepted-1',
    recipient: 'buyer@example.com',
    subject: 'Тестовое письмо',
    body: 'Проверка H1',
);
$first = $queue->queue($input);
$duplicate = $queue->queue($input);
if ($first->id !== $duplicate->id || 'pending' !== $first->status) {
    throw new RuntimeException('Persistent deduplication failed.');
}
try {
    $queue->queue(new EmailNotificationInputDto(
        consumer: 'e2e.contract',
        deduplicationKey: 'accepted-1',
        recipient: 'buyer@example.com',
        subject: 'Другое письмо',
        body: 'Другой payload',
    ));
    throw new RuntimeException('Deduplication conflict was not raised.');
} catch (NotificationDeduplicationConflictException) {
}
(new DeliverEmailUseCase(new DeliveryOperationRepository(), $acceptedTransport, $clock))->execute($first->id);
$accepted = $queue->status($first->id);
if (null === $accepted || 'accepted' !== $accepted->status || 1 !== $accepted->attempts) {
    throw new RuntimeException('Restart-safe accepted transition failed.');
}

$failed = $queue->queue(new EmailNotificationInputDto(
    consumer: 'e2e.contract',
    deduplicationKey: 'failed-1',
    recipient: 'buyer@example.com',
    subject: 'Отказ',
    body: 'Проверка лимита',
    maxAttempts: 1,
));
(new DeliverEmailUseCase($repository, $rejectedTransport, $clock))->execute($failed->id);
if ('failed' !== $queue->status($failed->id)?->status) {
    throw new RuntimeException('Retry exhaustion failed.');
}

$unknown = $queue->queue(new EmailNotificationInputDto(
    consumer: 'e2e.contract',
    deduplicationKey: 'unknown-1',
    recipient: 'buyer@example.com',
    subject: 'Неизвестный исход',
    body: 'Проверка timeout',
));
(new DeliverEmailUseCase($repository, $unknownTransport, $clock))->execute($unknown->id);
if ('unknown' !== $queue->status($unknown->id)?->status) {
    throw new RuntimeException('Unknown outcome was not quarantined.');
}
$recoveryPublisher = new class implements NotificationPublisherInterface {
    public int $published = 0;

    public function publish(string $operationId): void
    {
        ++$this->published;
    }
};
$dispatch = new DispatchPendingEmailUseCase($repository, $recoveryPublisher, $clock, new NullLogger());
if (0 !== $dispatch->execute(100) || 0 !== $recoveryPublisher->published) {
    throw new RuntimeException('Unknown outcome retried without explicit confirmation.');
}
if (1 > $dispatch->execute(100, true) || 1 > $recoveryPublisher->published) {
    throw new RuntimeException('Explicit unknown recovery did not republish.');
}

$connection = Application::getConnection();
$operationCount = $connection->query("SELECT COUNT(*) AS CNT FROM b_rebit_notification_operation WHERE CONSUMER_KEY='e2e.contract'")->fetch();
$attemptCount = $connection->query('SELECT COUNT(*) AS CNT FROM b_rebit_notification_attempt')->fetch();
if (!is_array($operationCount) || 3 !== (int)$operationCount['CNT']
    || !is_array($attemptCount) || 3 !== (int)$attemptCount['CNT']) {
    throw new RuntimeException('Persistent operation/attempt journal is incomplete.');
}

$dsn = (string)getenv('MESSENGER_TRANSPORT_DSN');
$queueName = MessengerQueueEnum::NOTIFICATION_EMAIL;
$transport = (new AmqpConnectionFactory($dsn))->create($queueName);
$bus = MessengerBusFactory::create(new MessengerBusConfigDto(
    handlers: [],
    routing: [DeliverEmailMessage::class => [$queueName->value]],
    transports: [$queueName->value => $transport],
));
$queueOperationId = '99999999-9999-4999-8999-999999999999';
$bus->dispatch(new DeliverEmailMessage($queueOperationId));
$received = null;
for ($attempt = 0; $attempt < 20; ++$attempt) {
    foreach ($transport->get() as $envelope) {
        $received = $envelope;
        break 2;
    }
    usleep(100000);
}
if (!$received instanceof Envelope) {
    throw new RuntimeException('Notification RabbitMQ message was not received.');
}
$message = $received->getMessage();
if (!$message instanceof DeliverEmailMessage || $queueOperationId !== $message->operationId) {
    throw new RuntimeException('Notification RabbitMQ message payload is invalid.');
}
$transport->ack($received);

echo "Notification H1 integration passed.\n";
