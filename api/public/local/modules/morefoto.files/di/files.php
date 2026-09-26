<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
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
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\Service\FileAccessPolicy;
use Morefoto\Files\Infrastructure\Database\BitrixDownloadRepository;
use Morefoto\Files\Infrastructure\Database\MysqlOrderDownloadGuard;
use Morefoto\Files\Infrastructure\File\LocalProtectedStorage;
use Morefoto\Files\Infrastructure\File\ZipArchiveBuilder;
use Morefoto\Files\Infrastructure\Id\DownloadIdGenerator;
use Morefoto\Files\Infrastructure\Messenger\FilesMessengerFactory;
use Morefoto\Files\Infrastructure\Messenger\FilesPublisher;
use Morefoto\Files\Infrastructure\Security\HmacDownloadToken;
use Morefoto\Files\Presentation\Command\DispatchPendingDownloadsCommand;
use Morefoto\Files\Presentation\Command\FilesConsumerCommand;
use Morefoto\Files\Presentation\Command\PurgeDownloadsCommand;
use Morefoto\Files\Presentation\Controller\PublicFileController;
use Morefoto\Files\Presentation\Files\FilesInputMapper;
use Morefoto\Files\Presentation\Files\FilesResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Contracts\Commerce\OrderEntitlementInterface;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    FileAccessPolicy::class => ['className' => FileAccessPolicy::class],
    DownloadRepositoryInterface::class => ['className' => BitrixDownloadRepository::class],
    OrderDownloadGuardInterface::class => ['className' => MysqlOrderDownloadGuard::class],
    ArchiveBuilderInterface::class => ['className' => ZipArchiveBuilder::class],
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
    PurgeDownloadsCommand::class => [
        'className' => PurgeDownloadsCommand::class,
        'constructorParams' => static fn(): array => [ServiceLocator::getInstance()->get(PurgeDownloadsUseCase::class)],
    ],
];
