<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Morefoto\Media\Application\Gallery\Service\GalleryLinks;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Infrastructure\Handoff\GroupMaterials;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;

return [
    GalleryLinkInterface::class => [
        'constructor' => static fn(): GalleryLinkInterface => new GalleryLinks(
            ServiceLocator::getInstance()->get(GalleryCapabilityRepository::class),
            ServiceLocator::getInstance()->get(GalleryCapabilityLifecycle::class),
        ),
    ],
    GroupMaterialsInterface::class => [
        'constructor' => static fn(): GroupMaterialsInterface => new GroupMaterials(
            ServiceLocator::getInstance()->get(MediaMutationRepository::class),
        ),
    ],
];
