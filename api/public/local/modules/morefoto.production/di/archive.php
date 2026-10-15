<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ArchiveWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ManifestWriterInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Application\Archive\Service\ArchiveBuilder;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Morefoto\Production\Application\Archive\UseCase\CreateProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ExportProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetGroupProductionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ListProductionGroupsUseCase;
use Morefoto\Production\Application\Archive\UseCase\OpenProductionArchiveUseCase;
use Morefoto\Production\Application\Archive\UseCase\RenewProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Morefoto\Production\Infrastructure\Access\ProductionAccess;
use Morefoto\Production\Infrastructure\Database\BitrixArchiveRepository;
use Morefoto\Production\Infrastructure\File\CsvManifestWriter;
use Morefoto\Production\Infrastructure\File\LocalArchiveStorage;
use Morefoto\Production\Infrastructure\File\ZipArchiveWriter;
use Morefoto\Production\Infrastructure\Id\ProductionIdGenerator;
use Morefoto\Production\Infrastructure\Security\HmacArchiveLink;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintClosedGroupsInterface;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    ArchivePolicy::class => ['className' => ArchivePolicy::class],
    ArchiveLayout::class => ['className' => ArchiveLayout::class],
    ProductionStatePolicy::class => ['className' => ProductionStatePolicy::class],
    ArchiveRepositoryInterface::class => ['className' => BitrixArchiveRepository::class],
    ManifestWriterInterface::class => ['className' => CsvManifestWriter::class],
    ProductionIdGeneratorInterface::class => ['className' => ProductionIdGenerator::class],
    ArchiveWriterInterface::class => [
        'constructor' => static fn(): ArchiveWriterInterface => new ZipArchiveWriter(new AtomicZipWriter()),
    ],
    ArchiveStorageInterface::class => [
        // The same private files root as J1: nginx serves both through /_protected/files/.
        'constructor' => static function(): ArchiveStorageInterface {
            $root = (string)getenv('MOREFOTO_PRIVATE_FILES_PATH');

            return new LocalArchiveStorage('' === $root ? dirname(__DIR__, 5) . '/var/private/files' : $root);
        },
    ],
    ArchiveLinkInterface::class => [
        'constructor' => static fn(): ArchiveLinkInterface => new HmacArchiveLink((string)(getenv('REBIT_ENCRYPTION_KEY') ?: '')),
    ],
    ProductionAccessInterface::class => [
        'constructor' => static fn(): ProductionAccessInterface => new ProductionAccess(
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
        ),
    ],
    ProductionLookup::class => [
        'className' => ProductionLookup::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
        ],
    ],
    VersionSnapshot::class => [
        'className' => VersionSnapshot::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(GroupPrintOrdersInterface::class),
            ServiceLocator::getInstance()->get(ArchiveLayout::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ProductionIdGeneratorInterface::class),
        ],
    ],
    VersionView::class => [
        'className' => VersionView::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveLinkInterface::class),
        ],
    ],
    ArchiveBuilder::class => [
        'className' => ArchiveBuilder::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(ArchiveWriterInterface::class),
            ServiceLocator::getInstance()->get(ManifestWriterInterface::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ArchiveLinkInterface::class),
            ServiceLocator::getInstance()->get(ArchivePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::production),
        ],
    ],
    RunProductionUseCase::class => [
        'className' => RunProductionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(PrintClosedGroupsInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(VersionSnapshot::class),
            ServiceLocator::getInstance()->get(ArchiveBuilder::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ArchivePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::production),
        ],
    ],
    ListProductionGroupsUseCase::class => [
        'className' => ListProductionGroupsUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ProductionStatePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    GetGroupProductionUseCase::class => [
        'className' => GetGroupProductionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(GroupPrintOrdersInterface::class),
            ServiceLocator::getInstance()->get(VersionView::class),
            ServiceLocator::getInstance()->get(ProductionStatePolicy::class),
            ServiceLocator::getInstance()->get(ArchivePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    CreateProductionVersionUseCase::class => [
        'className' => CreateProductionVersionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(VersionSnapshot::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(VersionView::class),
            ServiceLocator::getInstance()->get(ProductionStatePolicy::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    GetProductionVersionUseCase::class => [
        'className' => GetProductionVersionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(VersionView::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    ExportProductionVersionUseCase::class => [
        'className' => ExportProductionVersionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(ManifestWriterInterface::class),
        ],
    ],
    RenewProductionVersionUseCase::class => [
        'className' => RenewProductionVersionUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ProductionAccessInterface::class),
            ServiceLocator::getInstance()->get(ProductionLookup::class),
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveLinkInterface::class),
            ServiceLocator::getInstance()->get(VersionView::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
    OpenProductionArchiveUseCase::class => [
        'className' => OpenProductionArchiveUseCase::class,
        'constructorParams' => static fn(): array => [
            ServiceLocator::getInstance()->get(ArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(ArchiveLinkInterface::class),
            ServiceLocator::getInstance()->get(ArchiveStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ],
    ],
];
