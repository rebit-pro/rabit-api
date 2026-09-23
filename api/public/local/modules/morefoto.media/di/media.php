<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Contract\PreviewRendererInterface;
use Morefoto\Media\Application\Photo\Contract\PrivatePhotoStorageInterface;
use Morefoto\Media\Application\Photo\Message\Handler\ProcessPhotoMessageHandler;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Application\Photo\UseCase\AssignPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\ConsumeMediaUseCase;
use Morefoto\Media\Application\Photo\UseCase\DispatchPendingPhotoJobsUseCase;
use Morefoto\Media\Application\Photo\UseCase\GetPhotoUseCase;
use Morefoto\Media\Application\Photo\UseCase\ListPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\SetGroupCoverUseCase;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Infrastructure\Database\BitrixMediaTransaction;
use Morefoto\Media\Infrastructure\File\GdPreviewRenderer;
use Morefoto\Media\Infrastructure\File\LocalPrivatePhotoStorage;
use Morefoto\Media\Infrastructure\File\PhotoFileInspector;
use Morefoto\Media\Infrastructure\Handoff\StaffChildReference;
use Morefoto\Media\Infrastructure\Messenger\MediaMessengerFactory;
use Morefoto\Media\Infrastructure\Messenger\MediaPublisher;
use Morefoto\Media\Presentation\Command\DispatchPendingMediaCommand;
use Morefoto\Media\Presentation\Command\MediaConsumerCommand;
use Morefoto\Media\Presentation\Controller\MediaController;
use Morefoto\Media\Presentation\Controller\PhotoListController;
use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoListResultMapper;
use Morefoto\Media\Presentation\Request\MediaRequestFactory;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Application\Contract\Messenger\MessageConsumerRunnerInterface;
use Rebit\Share\Application\Contract\Messenger\MessageTransportFactoryInterface;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Infrastructure\Messenger\AmqpConnectionFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Enum\MessengerQueueEnum;
use Rebit\Share\Shared\Facade\Log;
use Symfony\Component\Messenger\Transport\TransportInterface;

return [
    StaffChildReferenceInterface::class => ['constructor' => static fn(): StaffChildReferenceInterface => new StaffChildReference()],
    PhotoRepository::class => ['className' => PhotoRepository::class],
    MediaMutationRepository::class => ['className' => MediaMutationRepository::class],
    MediaTransactionInterface::class => ['className' => BitrixMediaTransaction::class],
    PhotoFileInspector::class => ['className' => PhotoFileInspector::class],
    PhotoRowMapper::class => ['className' => PhotoRowMapper::class],
    MediaRequestFactory::class => ['className' => MediaRequestFactory::class],
    PhotoListInputMapper::class => ['className' => PhotoListInputMapper::class],
    PhotoListResultMapper::class => ['className' => PhotoListResultMapper::class],
    PrivatePhotoStorageInterface::class => [
        'constructor' => static function(): PrivatePhotoStorageInterface {
            $root = (string)getenv('MOREFOTO_PRIVATE_MEDIA_PATH');

            return new LocalPrivatePhotoStorage('' === $root ? dirname(__DIR__, 5) . '/var/private/media' : $root);
        },
    ],
    PreviewRendererInterface::class => [
        'constructor' => static function(): PreviewRendererInterface {
            $root = (string)getenv('MOREFOTO_PUBLIC_PREVIEW_PATH');
            $url = (string)getenv('MOREFOTO_PUBLIC_PREVIEW_URL');

            return new GdPreviewRenderer(
                '' === $root ? dirname(__DIR__, 4) . '/upload/morefoto/previews' : $root,
                '' === $url ? '/upload/morefoto/previews' : $url,
            );
        },
    ],
    MessengerQueueEnum::MEDIA_PROCESSING->transportKey() => [
        'constructor' => static fn(): TransportInterface => ServiceLocator::getInstance()
            ->get(AmqpConnectionFactory::class)
            ->create(MessengerQueueEnum::MEDIA_PROCESSING),
    ],
    MediaPublisherInterface::class => [
        'constructor' => static fn(): MediaPublisherInterface => new MediaPublisher(
            MediaMessengerFactory::createPublisher(ServiceLocator::getInstance()),
        ),
    ],
    ProcessPhotoMessageHandler::class => [
        'className' => ProcessPhotoMessageHandler::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PhotoRepository::class),
            ServiceLocator::getInstance()->get(PrivatePhotoStorageInterface::class),
            ServiceLocator::getInstance()->get(PreviewRendererInterface::class),
            Log::channel(LogChannelEnum::media),
        ],
    ],
    UploadPhotoUseCase::class => [
        'className' => UploadPhotoUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(AccessGuardInterface::class),
            ServiceLocator::getInstance()->get(PhotoFileInspector::class),
            ServiceLocator::getInstance()->get(PrivatePhotoStorageInterface::class),
            ServiceLocator::getInstance()->get(PhotoRepository::class),
            ServiceLocator::getInstance()->get(MediaPublisherInterface::class),
            Log::channel(LogChannelEnum::media),
        ],
    ],
    ListPhotosUseCase::class => [
        'className' => ListPhotosUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PhotoRepository::class),
            ServiceLocator::getInstance()->get(MediaMutationRepository::class),
            ServiceLocator::getInstance()->get(PhotoRowMapper::class),
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(AccessGuardInterface::class),
        ],
    ],
    AssignPhotosUseCase::class => [
        'className' => AssignPhotosUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MediaTransactionInterface::class),
            ServiceLocator::getInstance()->get(MediaMutationRepository::class),
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(AccessGuardInterface::class),
        ],
    ],
    SetGroupCoverUseCase::class => [
        'className' => SetGroupCoverUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MediaTransactionInterface::class),
            ServiceLocator::getInstance()->get(MediaMutationRepository::class),
            ServiceLocator::getInstance()->get(GroupReferenceInterface::class),
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(AccessGuardInterface::class),
        ],
    ],
    GetPhotoUseCase::class => [
        'className' => GetPhotoUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PhotoRepository::class),
            ServiceLocator::getInstance()->get(PhotoRowMapper::class),
            ServiceLocator::getInstance()->get(MediaScopeInterface::class),
            ServiceLocator::getInstance()->get(AccessGuardInterface::class),
        ],
    ],
    ConsumeMediaUseCase::class => [
        'className' => ConsumeMediaUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(MessageConsumerRunnerInterface::class),
            ServiceLocator::getInstance()->get(MessageTransportFactoryInterface::class),
            MediaMessengerFactory::createBus(ServiceLocator::getInstance()),
        ],
    ],
    DispatchPendingPhotoJobsUseCase::class => [
        'className' => DispatchPendingPhotoJobsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(PhotoRepository::class),
            ServiceLocator::getInstance()->get(MediaPublisherInterface::class),
            Log::channel(LogChannelEnum::media),
        ],
    ],
    PhotoListController::class => [
        'className' => PhotoListController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ListPhotosUseCase::class),
            ServiceLocator::getInstance()->get(PhotoListInputMapper::class),
            ServiceLocator::getInstance()->get(PhotoListResultMapper::class),
        ],
    ],
    MediaController::class => [
        'className' => MediaController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(UploadPhotoUseCase::class),
            ServiceLocator::getInstance()->get(GetPhotoUseCase::class),
            ServiceLocator::getInstance()->get(AssignPhotosUseCase::class),
            ServiceLocator::getInstance()->get(SetGroupCoverUseCase::class),
            ServiceLocator::getInstance()->get(MediaRequestFactory::class),
            ServiceLocator::getInstance()->get(TokenResolverInterface::class),
        ],
    ],
    MediaConsumerCommand::class => [
        'className' => MediaConsumerCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ConsumeMediaUseCase::class),
        ],
    ],
    DispatchPendingMediaCommand::class => [
        'className' => DispatchPendingMediaCommand::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DispatchPendingPhotoJobsUseCase::class),
        ],
    ],
];
