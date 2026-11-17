<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveBuilder;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveView;
use Morefoto\Production\Application\Institution\Service\InstitutionGroups;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Application\Institution\UseCase\CreateInstitutionSupplementUseCase;
use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\ExportInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\GetInstitutionProductionUseCase;
use Morefoto\Production\Application\Institution\UseCase\RenewInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\RunInstitutionProductionUseCase;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Infrastructure\Database\BitrixInstitutionArchiveRepository;
use Morefoto\Production\Presentation\Controller\InstitutionArchiveContentController;
use Morefoto\Production\Presentation\Controller\InstitutionProductionController;
use Morefoto\Production\Presentation\Institution\InstitutionResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;

return [
    InstitutionArchiveRepositoryInterface::class => ['className' => BitrixInstitutionArchiveRepository::class],
    InstitutionGroups::class => [
        'className' => InstitutionGroups::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(ArchivePolicy::class),
        ],
    ],
    InstitutionSnapshot::class => [
        'className' => InstitutionSnapshot::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(VersionSnapshot::class),
            ServiceLocator::getInstance()->get(InstitutionGroups::class),
            ServiceLocator::getInstance()->get(ArchiveLayout::class),
            ServiceLocator::getInstance()->get(PhotoFilenamesInterface::class),
            ServiceLocator::getInstance()->get(ProductionIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    InstitutionArchiveAccess::class => [
        'className' => InstitutionArchiveAccess::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(PrintInstitutionDirectoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
        ],
    ],
    InstitutionArchiveView::class => [
        'className' => InstitutionArchiveView::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    InstitutionArchiveBuilder::class => [
        'className' => InstitutionArchiveBuilder::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionSnapshot::class),
            ServiceLocator::getInstance()->get(InstitutionGroups::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(ArchiveWriterInterface::class),
            ServiceLocator::getInstance()->get(ManifestWriterInterface::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ProductionIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(ArchivePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    RunInstitutionProductionUseCase::class => [
        'className' => RunInstitutionProductionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PrintInstitutionDirectoryInterface::class),
            ServiceLocator::getInstance()->get(InstitutionSnapshot::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveBuilder::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    GetInstitutionProductionUseCase::class => [
        'className' => GetInstitutionProductionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(InstitutionGroups::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(GroupPrintOrdersInterface::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveView::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    CreateInstitutionSupplementUseCase::class => [
        'className' => CreateInstitutionSupplementUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(InstitutionSnapshot::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveView::class),
        ],
    ],
    RenewInstitutionArchiveUseCase::class => [
        'className' => RenewInstitutionArchiveUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(InstitutionArchiveView::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    DownloadInstitutionArchiveUseCase::class => [
        'className' => DownloadInstitutionArchiveUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(ArchiveLinkInterface::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    ExportInstitutionArchiveUseCase::class => [
        'className' => ExportInstitutionArchiveUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(InstitutionArchiveAccess::class),
            ServiceLocator::getInstance()->get(InstitutionSnapshot::class),
            ServiceLocator::getInstance()->get(ManifestWriterInterface::class),
        ],
    ],
    InstitutionResultMapper::class => ['className' => InstitutionResultMapper::class],
    InstitutionProductionController::class => [
        'className' => InstitutionProductionController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GetInstitutionProductionUseCase::class),
            ServiceLocator::getInstance()->get(CreateInstitutionSupplementUseCase::class),
            ServiceLocator::getInstance()->get(RenewInstitutionArchiveUseCase::class),
            ServiceLocator::getInstance()->get(DownloadInstitutionArchiveUseCase::class),
            ServiceLocator::getInstance()->get(ExportInstitutionArchiveUseCase::class),
            ServiceLocator::getInstance()->get(InstitutionResultMapper::class),
        ],
    ],
    InstitutionArchiveContentController::class => [
        'className' => InstitutionArchiveContentController::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(DownloadInstitutionArchiveUseCase::class),
        ],
    ],
];
