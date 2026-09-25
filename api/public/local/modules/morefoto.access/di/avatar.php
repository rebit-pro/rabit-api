<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Avatar\Contract\AvatarInspectorInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarRendererInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Morefoto\Access\Application\Avatar\Service\AvatarAccess;
use Morefoto\Access\Application\Avatar\UseCase\DeleteStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\GetStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\SaveStaffAvatarUseCase;
use Morefoto\Access\Infrastructure\Avatar\AvatarFileInspector;
use Morefoto\Access\Infrastructure\Avatar\GdAvatarRenderer;
use Morefoto\Access\Infrastructure\Avatar\LocalAvatarStorage;
use Morefoto\Access\Infrastructure\Avatar\SqlStaffAvatarRepository;
use Morefoto\Access\Presentation\Avatar\AvatarInputMapper;
use Morefoto\Access\Presentation\Controller\StaffAvatarController;

return [
    StaffAvatarRepositoryInterface::class => ['className' => SqlStaffAvatarRepository::class],
    AvatarInspectorInterface::class => ['className' => AvatarFileInspector::class],
    AvatarRendererInterface::class => ['className' => GdAvatarRenderer::class],
    AvatarStorageInterface::class => [
        'constructor' => static function(): AvatarStorageInterface {
            // Avatars sit next to private photo originals unless a dedicated path is configured.
            $root = (string)getenv('MOREFOTO_AVATAR_PATH');
            if ('' === $root) {
                $media = (string)getenv('MOREFOTO_PRIVATE_MEDIA_PATH');
                $root = ('' === $media ? dirname(__DIR__, 5) . '/var/private/media' : $media) . '/avatars';
            }

            return new LocalAvatarStorage($root);
        },
    ],
    AvatarOutputMapper::class => ['className' => AvatarOutputMapper::class],
    AvatarAccess::class => [
        'className' => AvatarAccess::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(StaffAuthorization::class),
        ],
    ],
    SaveStaffAvatarUseCase::class => [
        'className' => SaveStaffAvatarUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AvatarAccess::class),
            ServiceLocator::getInstance()->get(StaffAvatarRepositoryInterface::class),
            ServiceLocator::getInstance()->get(AvatarInspectorInterface::class),
            ServiceLocator::getInstance()->get(AvatarRendererInterface::class),
            ServiceLocator::getInstance()->get(AvatarStorageInterface::class),
            ServiceLocator::getInstance()->get(AvatarOutputMapper::class),
        ],
    ],
    DeleteStaffAvatarUseCase::class => [
        'className' => DeleteStaffAvatarUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AvatarAccess::class),
            ServiceLocator::getInstance()->get(StaffAvatarRepositoryInterface::class),
            ServiceLocator::getInstance()->get(AvatarStorageInterface::class),
        ],
    ],
    GetStaffAvatarUseCase::class => [
        'className' => GetStaffAvatarUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(AvatarAccess::class),
            ServiceLocator::getInstance()->get(StaffAvatarRepositoryInterface::class),
            ServiceLocator::getInstance()->get(AvatarStorageInterface::class),
        ],
    ],
    AvatarInputMapper::class => ['className' => AvatarInputMapper::class],
    StaffAvatarController::class => [
        'className' => StaffAvatarController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(SaveStaffAvatarUseCase::class),
            ServiceLocator::getInstance()->get(DeleteStaffAvatarUseCase::class),
            ServiceLocator::getInstance()->get(GetStaffAvatarUseCase::class),
            ServiceLocator::getInstance()->get(AvatarInputMapper::class),
        ],
    ],
];
