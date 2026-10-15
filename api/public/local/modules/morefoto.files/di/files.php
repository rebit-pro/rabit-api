<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Delivery\Service\DeliveryView;
use Morefoto\Files\Application\Delivery\Service\OrderEmailSender;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;
use Morefoto\Files\Application\Delivery\UseCase\GetFileDeliveryUseCase;
use Morefoto\Files\Application\Delivery\UseCase\GetOrderEmailUseCase;
use Morefoto\Files\Application\Delivery\UseCase\SendOrderEmailAgainUseCase;
use Morefoto\Files\Application\Files\Contract\ArchiveBuilderInterface;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;
use Morefoto\Files\Application\Files\Contract\FilesPublisherInterface;
use Morefoto\Files\Application\Files\Contract\OrderDownloadGuardInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Files\Message\Handler\BuildArchiveMessageHandler;
use Morefoto\Files\Application\Files\Service\DownloadView;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Application\Files\UseCase\ConsumeFilesUseCase;
use Morefoto\Files\Application\Files\UseCase\DispatchPendingDownloadsUseCase;
use Morefoto\Files\Application\Files\UseCase\GetDownloadUseCase;
use Morefoto\Files\Application\Files\UseCase\GetOrderFilesUseCase;
use Morefoto\Files\Application\Files\UseCase\OpenDownloadContentUseCase;
use Morefoto\Files\Application\Files\UseCase\PurgeDownloadsUseCase;
use Morefoto\Files\Application\Files\UseCase\RequestDownloadUseCase;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Infrastructure\Database\BitrixDeliveryRepository;
use Morefoto\Files\Infrastructure\Database\BitrixDownloadRepository;
use Morefoto\Files\Infrastructure\Database\MysqlOrderDownloadGuard;
use Morefoto\Files\Infrastructure\File\LocalProtectedStorage;
use Morefoto\Files\Infrastructure\File\ZipArchiveBuilder;
use Morefoto\Files\Infrastructure\Id\DownloadIdGenerator;
use Morefoto\Files\Infrastructure\Mail\OrderEmailComposer;
use Morefoto\Files\Infrastructure\Messenger\FilesMessengerFactory;
use Morefoto\Files\Infrastructure\Messenger\FilesPublisher;
use Morefoto\Files\Infrastructure\Security\HmacDownloadToken;
use Morefoto\Files\Presentation\Command\DispatchPendingDownloadsCommand;
use Morefoto\Files\Presentation\Command\FilesConsumerCommand;
use Morefoto\Files\Presentation\Command\OrderEmailsCommand;
use Morefoto\Files\Presentation\Command\PurgeDownloadsCommand;
use Morefoto\Files\Presentation\Controller\PublicFileController;
use Morefoto\Files\Presentation\Controller\PublicFileDeliveryController;
use Morefoto\Files\Presentation\Delivery\DeliveryInputMapper;
use Morefoto\Files\Presentation\Delivery\DeliveryResultMapper;
use Morefoto\Files\Presentation\Files\FilesInputMapper;
use Morefoto\Files\Presentation\Files\FilesResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;
use Rebit\Share\Contracts\Commerce\OrderEntitlementInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    FileAccessPolicy::class => ['className' => FileAccessPolicy::class],
    DownloadRepositoryInterface::class => ['className' => BitrixDownloadRepository::class],
    OrderDownloadGuardInterface::class => ['className' => MysqlOrderDownloadGuard::class],
    ArchiveBuilderInterface::class => [
        'constructor' => static fn(): ArchiveBuilderInterface => new ZipArchiveBuilder(new AtomicZipWriter()),
    ],
    DownloadIdGeneratorInterface::class => ['className' => DownloadIdGenerator::class],
    ProtectedStorageInterface::class => [
        'constructor' => static function(): ProtectedStorageInterface {
            $root = (string)getenv('MOREFOTO_PRIVATE_FILES_PATH');

            return new LocalProtectedStorage('' === $root ? dirname(__DIR__, 5) . '/var/private/files' : $root);
        },
    ],
    DownloadTokenInterface::class => [
        // The link key is derived from the server secret; the buyer's order key never enters a URL.
        'constructor' => static fn(): DownloadTokenInterface => new HmacDownloadToken((string)(getenv('REBIT_ENCRYPTION_KEY') ?: '')),
    ],
    MessengerQueueEnum::FILES_ARCHIVE->transportKey() => [
        'constructor' => static fn(): TransportInterface => ServiceLocator::getInstance()
            ->get(AmqpConnectionFactory::class)
            ->create(MessengerQueueEnum::FILES_ARCHIVE),
    ],
    FilesPublisherInterface::class => [
        'constructor' => static fn(): FilesPublisherInterface => new FilesPublisher(
            FilesMessengerFactory::createPublisher(ServiceLocator::getInstance()),
        ),
    ],
    OrderFileAccess::class => [
        'className' => OrderFileAccess::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderEntitlementInterface::class),
            ServiceLocator::getInstance()->get(ChildPhotosInterface::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(FileAccessPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    DownloadView::class => [
        'className' => DownloadView::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(FileAccessPolicy::class),
            ServiceLocator::getInstance()->get(DownloadTokenInterface::class),
        ],
    ],
    GetOrderFilesUseCase::class => [
        'className' => GetOrderFilesUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
        ],
    ],
    RequestDownloadUseCase::class => [
        'className' => RequestDownloadUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OrderDownloadGuardInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(FileAccessPolicy::class),
            ServiceLocator::getInstance()->get(DownloadView::class),
            ServiceLocator::getInstance()->get(DownloadIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(FilesPublisherInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ],
    ],
    GetDownloadUseCase::class => [
        'className' => GetDownloadUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DownloadView::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    OpenDownloadContentUseCase::class => [
        'className' => OpenDownloadContentUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DownloadTokenInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    BuildArchiveMessageHandler::class => [
        'className' => BuildArchiveMessageHandler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(ArchiveBuilderInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(FileAccessPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ],
    ],
    ConsumeFilesUseCase::class => [
        'className' => ConsumeFilesUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MessageConsumerRunnerInterface::class),
            ServiceLocator::getInstance()->get(MessageTransportFactoryInterface::class),
            FilesMessengerFactory::createBus(ServiceLocator::getInstance()),
        ],
    ],
    DispatchPendingDownloadsUseCase::class => [
        'className' => DispatchPendingDownloadsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(FilesPublisherInterface::class),
            ServiceLocator::getInstance()->get(FileAccessPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ],
    ],
    PurgeDownloadsUseCase::class => [
        'className' => PurgeDownloadsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DownloadRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ],
    ],
    FilesInputMapper::class => ['className' => FilesInputMapper::class],
    FilesResultMapper::class => ['className' => FilesResultMapper::class],
    PublicFileController::class => [
        'className' => PublicFileController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetOrderFilesUseCase::class),
            ServiceLocator::getInstance()->get(RequestDownloadUseCase::class),
            ServiceLocator::getInstance()->get(GetDownloadUseCase::class),
            ServiceLocator::getInstance()->get(OpenDownloadContentUseCase::class),
            ServiceLocator::getInstance()->get(FilesInputMapper::class),
            ServiceLocator::getInstance()->get(FilesResultMapper::class),
        ],
    ],
    FilesConsumerCommand::class => [
        'className' => FilesConsumerCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(ConsumeFilesUseCase::class)],
    ],
    DispatchPendingDownloadsCommand::class => [
        'className' => DispatchPendingDownloadsCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(DispatchPendingDownloadsUseCase::class)],
    ],
    // J2: the letter with the link to the paid order.
    DeliveryPolicy::class => ['className' => DeliveryPolicy::class],
    DeliveryRepositoryInterface::class => ['className' => BitrixDeliveryRepository::class],
    OrderEmailComposerInterface::class => [
        'constructor' => static fn(): OrderEmailComposerInterface => new OrderEmailComposer(
            (string)(getenv('MOREFOTO_APP_URL') ?: 'https://app.morefoto36.ru'),
        ),
    ],
    DeliveryView::class => [
        'className' => DeliveryView::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(EmailNotificationInterface::class)],
    ],
    OrderEmailSender::class => [
        'className' => OrderEmailSender::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliveryRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OrderEmailLinkInterface::class),
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(OrderEmailComposerInterface::class),
            ServiceLocator::getInstance()->get(EmailNotificationInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ],
    ],
    DispatchOrderEmailsUseCase::class => [
        'className' => DispatchOrderEmailsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DeliveryRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OrderEmailLinkInterface::class),
            ServiceLocator::getInstance()->get(OrderEmailSender::class),
            ServiceLocator::getInstance()->get(DownloadIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(DeliveryPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    SendOrderEmailAgainUseCase::class => [
        'className' => SendOrderEmailAgainUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(OrderEmailLinkInterface::class),
            ServiceLocator::getInstance()->get(DeliveryRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OrderEmailSender::class),
            ServiceLocator::getInstance()->get(DeliveryView::class),
            ServiceLocator::getInstance()->get(DownloadIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(DeliveryPolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    GetOrderEmailUseCase::class => [
        'className' => GetOrderEmailUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(OrderEmailLinkInterface::class),
            ServiceLocator::getInstance()->get(DeliveryRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DeliveryView::class),
            ServiceLocator::getInstance()->get(DeliveryPolicy::class),
        ],
    ],
    GetFileDeliveryUseCase::class => [
        'className' => GetFileDeliveryUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(OrderFileAccess::class),
            ServiceLocator::getInstance()->get(DeliveryRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DeliveryView::class),
        ],
    ],
    DeliveryInputMapper::class => ['className' => DeliveryInputMapper::class],
    DeliveryResultMapper::class => ['className' => DeliveryResultMapper::class],
    PublicFileDeliveryController::class => [
        'className' => PublicFileDeliveryController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetOrderEmailUseCase::class),
            ServiceLocator::getInstance()->get(SendOrderEmailAgainUseCase::class),
            ServiceLocator::getInstance()->get(GetFileDeliveryUseCase::class),
            ServiceLocator::getInstance()->get(DeliveryInputMapper::class),
            ServiceLocator::getInstance()->get(DeliveryResultMapper::class),
        ],
    ],
    OrderEmailsCommand::class => [
        'className' => OrderEmailsCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)],
    ],
    PurgeDownloadsCommand::class => [
        'className' => PurgeDownloadsCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(PurgeDownloadsUseCase::class)],
    ],
];
