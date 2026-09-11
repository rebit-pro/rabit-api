<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Rebit\Share\Application\Audit\Message\Handler\AuditMessageHandler;
use Rebit\Share\Application\Audit\Port\AuditPublisherInterface;
use Rebit\Share\Application\Audit\UseCase\ConsumeAuditUseCase;
use Rebit\Share\Domain\Audit\Repository\AuditLogRepository;
use Rebit\Share\Infrastructure\Audit\Messenger\AuditMessengerFactory;
use Rebit\Share\Infrastructure\Audit\Messenger\AuditPublisher;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Infrastructure\Messenger\ConsumerRunnerInterface;
use Rebit\Share\Presentation\Command\Audit\AuditConsumerCommand;
use Rebit\Share\Presentation\Command\Audit\TestAuditCommand;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    AuditLogRepository::class => [
        'className' => AuditLogRepository::class,
    ],
    AuditMessageHandler::class => [
        'className' => AuditMessageHandler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AuditLogRepository::class),
            Log::channel(LogChannelEnum::security),
        ],
    ],
    AuditPublisherInterface::class => [
        'constructor' => static fn(): AuditPublisherInterface => new AuditPublisher(
            AuditMessengerFactory::createPublisher(ServiceLocator::getInstance()),
        ),
    ],
    ConsumeAuditUseCase::class => [
        'className' => ConsumeAuditUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ConsumerRunnerInterface::class),
            ServiceLocator::getInstance()->get(AmqpConnectionFactory::class),
            AuditMessengerFactory::createBus(ServiceLocator::getInstance()),
        ],
    ],
    AuditConsumerCommand::class => [
        'className' => AuditConsumerCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ConsumeAuditUseCase::class),
        ],
    ],
    TestAuditCommand::class => [
        'className' => TestAuditCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AuditPublisherInterface::class),
        ],
    ],
];
