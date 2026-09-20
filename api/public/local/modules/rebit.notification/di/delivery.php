<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Notification\Application\Delivery\Contract\DeliveryOperationRepositoryInterface;
use Rebit\Notification\Application\Delivery\Contract\EmailTransportInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationClockInterface;
use Rebit\Notification\Application\Delivery\Contract\NotificationPublisherInterface;
use Rebit\Notification\Application\Delivery\Message\Handler\DeliverEmailMessageHandler;
use Rebit\Notification\Application\Delivery\UseCase\ConsumeEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\DispatchPendingEmailUseCase;
use Rebit\Notification\Application\Delivery\UseCase\QueueEmailUseCase;
use Rebit\Notification\Infrastructure\Email\BitrixEmailTransport;
use Rebit\Notification\Infrastructure\Messenger\NotificationMessengerFactory;
use Rebit\Notification\Infrastructure\Messenger\NotificationPublisher;
use Rebit\Notification\Infrastructure\Persistence\DeliveryOperationRepository;
use Rebit\Notification\Infrastructure\Time\ServerNotificationClock;
use Rebit\Notification\Presentation\Command\DispatchPendingNotificationsCommand;
use Rebit\Notification\Presentation\Command\NotificationConsumerCommand;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    DeliveryOperationRepositoryInterface::class => [
        'constructor' => static fn(): DeliveryOperationRepositoryInterface => new DeliveryOperationRepository(),
    ],
    NotificationClockInterface::class => [
        'constructor' => static fn(): NotificationClockInterface => new ServerNotificationClock(),
    ],
    EmailTransportInterface::class => [
        'constructor' => static fn(): EmailTransportInterface => new BitrixEmailTransport(
            (string)(getenv('REBIT_NOTIFICATION_EMAIL_SITE_ID') ?: 's1'),
        ),
    ],
    MessengerQueueEnum::NOTIFICATION_EMAIL->transportKey() => [
        'constructor' => static fn(): TransportInterface => ServiceLocator::getInstance()
            ->get(AmqpConnectionFactory::class)
            ->create(MessengerQueueEnum::NOTIFICATION_EMAIL),
    ],
    NotificationPublisherInterface::class => [
        'constructor' => static fn(): NotificationPublisherInterface => new NotificationPublisher(
            NotificationMessengerFactory::createPublisher(ServiceLocator::getInstance()),
        ),
    ],
    QueueEmailUseCase::class => [
        'className' => QueueEmailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliveryOperationRepositoryInterface::class),
            ServiceLocator::getInstance()->get(NotificationPublisherInterface::class),
            ServiceLocator::getInstance()->get(NotificationClockInterface::class),
            Log::channel(LogChannelEnum::notification),
        ],
    ],
    EmailNotificationInterface::class => [
        'constructor' => static fn(): EmailNotificationInterface => ServiceLocator::getInstance()->get(QueueEmailUseCase::class),
    ],
    DeliverEmailUseCase::class => [
        'className' => DeliverEmailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliveryOperationRepositoryInterface::class),
            ServiceLocator::getInstance()->get(EmailTransportInterface::class),
            ServiceLocator::getInstance()->get(NotificationClockInterface::class),
        ],
    ],
    DispatchPendingEmailUseCase::class => [
        'className' => DispatchPendingEmailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliveryOperationRepositoryInterface::class),
            ServiceLocator::getInstance()->get(NotificationPublisherInterface::class),
            ServiceLocator::getInstance()->get(NotificationClockInterface::class),
            Log::channel(LogChannelEnum::notification),
        ],
    ],
    DeliverEmailMessageHandler::class => [
        'className' => DeliverEmailMessageHandler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliverEmailUseCase::class),
        ],
    ],
    ConsumeEmailUseCase::class => [
        'className' => ConsumeEmailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MessageConsumerRunnerInterface::class),
            ServiceLocator::getInstance()->get(MessageTransportFactoryInterface::class),
            NotificationMessengerFactory::createBus(ServiceLocator::getInstance()),
        ],
    ],
    NotificationConsumerCommand::class => [
        'className' => NotificationConsumerCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ConsumeEmailUseCase::class),
        ],
    ],
    DispatchPendingNotificationsCommand::class => [
        'className' => DispatchPendingNotificationsCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DispatchPendingEmailUseCase::class),
        ],
    ],
];
