<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Media\Application\Gallery\Contract\PreviewContentInterface;
use Morefoto\Media\Application\Gallery\Service\ShootGalleryLinks;
use Morefoto\Media\Application\Gallery\UseCase\GetShootGalleryUseCase;
use Morefoto\Media\Domain\Gallery\Repository\ShootGalleryRepository;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use Morefoto\Media\Presentation\Controller\ShootGalleryController;
use Morefoto\Media\Presentation\Gallery\ShootGalleryResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\ShootGalleryLinkInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;

$services = [
    ShootGalleryRepository::class => ['className' => ShootGalleryRepository::class],
    ShootGalleryResultMapper::class => ['className' => ShootGalleryResultMapper::class],
    ShootGalleryLinkInterface::class => ['constructor' => static fn(): ShootGalleryLinkInterface => new ShootGalleryLinks(
        ServiceLocator::getInstance()->get(ShootGalleryRepository::class),
        ServiceLocator::getInstance()->get(GalleryLinkInterface::class),
    )],
];
foreach ([
    GetShootGalleryUseCase::class => [ShootGalleryRepository::class, GroupDirectoryInterface::class, MediaScopeInterface::class, GalleryAvailability::class, ClockInterface::class, PreviewContentInterface::class],
    ShootGalleryController::class => [GetShootGalleryUseCase::class, ShootGalleryResultMapper::class],
] as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
