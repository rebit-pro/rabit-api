<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Application\Gallery\Service\GalleryAccess;
use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Morefoto\Media\Application\Gallery\UseCase\GetGalleryPreviewUseCase;
use Morefoto\Media\Application\Gallery\UseCase\GetGalleryUseCase;
use Morefoto\Media\Application\Gallery\UseCase\GetManagedPreviewUseCase;
use Morefoto\Media\Application\Photo\UseCase\GetPhotoUseCase;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use Morefoto\Media\Infrastructure\File\PreviewContent;
use Morefoto\Media\Presentation\Controller\GalleryController;
use Morefoto\Media\Presentation\Controller\ManagedPreviewController;
use Morefoto\Media\Presentation\Gallery\GalleryResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;
use Rebit\Share\Infrastructure\Clock\SystemClock;

return [
    ClockInterface::class => [
        'constructor' => static fn(): ClockInterface => new SystemClock(),
    ],
    GalleryAccessInterface::class => [
        'constructor' => static fn(): GalleryAccessInterface => ServiceLocator::getInstance()->get(GalleryAccess::class),
    ],
    GalleryCapabilityRepository::class => [
        'className' => GalleryCapabilityRepository::class,
    ],
    GalleryPhotoRepository::class => [
        'className' => GalleryPhotoRepository::class,
    ],
    GalleryAvailability::class => [
        'className' => GalleryAvailability::class,
    ],
    GalleryResultMapper::class => [
        'className' => GalleryResultMapper::class,
    ],
    GalleryCapabilityLifecycle::class => [
        'className' => GalleryCapabilityLifecycle::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryCapabilityRepository::class),
            ServiceLocator::getInstance()->get(GalleryGroupInterface::class),
        ],
    ],
    GalleryAccess::class => [
        'className' => GalleryAccess::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryCapabilityRepository::class),
            ServiceLocator::getInstance()->get(GalleryGroupInterface::class),
            ServiceLocator::getInstance()->get(GalleryPhotoRepository::class),
            ServiceLocator::getInstance()->get(GalleryAvailability::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    GetGalleryUseCase::class => [
        'className' => GetGalleryUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryAccessInterface::class),
        ],
    ],
    GalleryController::class => [
        'className' => GalleryController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetGalleryUseCase::class),
            ServiceLocator::getInstance()->get(GalleryResultMapper::class),
            ServiceLocator::getInstance()->get(GetGalleryPreviewUseCase::class),
        ],
    ],
    PreviewContentInterface::class => [
        'constructor' => static function(): PreviewContentInterface {
            $root = (string)getenv('MOREFOTO_PUBLIC_PREVIEW_PATH');

            return new PreviewContent('' === $root ? dirname(__DIR__, 4) . '/upload/morefoto/previews' : $root);
        },
    ],
    GetGalleryPreviewUseCase::class => [
        'className' => GetGalleryPreviewUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GalleryAccessInterface::class),
            ServiceLocator::getInstance()->get(GalleryPhotoRepository::class),
            ServiceLocator::getInstance()->get(PreviewContentInterface::class),
        ],
    ],
    GetManagedPreviewUseCase::class => [
        'className' => GetManagedPreviewUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetPhotoUseCase::class),
            ServiceLocator::getInstance()->get(PreviewContentInterface::class),
        ],
    ],
    ManagedPreviewController::class => [
        'className' => ManagedPreviewController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetManagedPreviewUseCase::class),
        ],
    ],
];
