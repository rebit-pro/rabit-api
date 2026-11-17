<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\DownloadTokenInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchivePublisherInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Application\Institution\Message\Handler\BuildInstitutionDigitalArchiveMessageHandler;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveAccess;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveBuild;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveSnapshot;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;
use Morefoto\Files\Application\Institution\UseCase\CreateInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\DownloadInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ExportInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\GetInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ListInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Application\Institution\UseCase\MaintainInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\Service\DigitalArchiveLayout;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Infrastructure\Database\BitrixDigitalArchiveRepository;
use Morefoto\Files\Infrastructure\File\DigitalArchiveWriter;
use Morefoto\Files\Infrastructure\Messenger\DigitalArchivePublisher;
use Morefoto\Files\Infrastructure\Messenger\FilesMessengerFactory;
use Morefoto\Files\Presentation\Controller\InstitutionDigitalArchiveContentController;
use Morefoto\Files\Presentation\Controller\InstitutionDigitalArchiveController;
use Morefoto\Files\Presentation\Institution\DigitalArchiveResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Commerce\PurchasedDigitalLinesInterface;
use Rebit\Share\Contracts\Media\BoundedChildPhotosInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;

return [
    DigitalArchivePolicy::class => ['className' => DigitalArchivePolicy::class],
    DigitalArchiveLayout::class => ['className' => DigitalArchiveLayout::class],
    DigitalArchiveRepositoryInterface::class => ['constructor' => static fn(): DigitalArchiveRepositoryInterface => new BitrixDigitalArchiveRepository()],
    DigitalArchiveWriterInterface::class => ['constructor' => static fn(): DigitalArchiveWriterInterface => new DigitalArchiveWriter()],
    DigitalArchivePublisherInterface::class => [
        'constructor' => static fn(): DigitalArchivePublisherInterface => new DigitalArchivePublisher(FilesMessengerFactory::createPublisher(ServiceLocator::getInstance())),
    ],
    DigitalArchiveAccess::class => [
        'constructor' => static fn(): DigitalArchiveAccess => new DigitalArchiveAccess(
            ServiceLocator::getInstance()->get(InstitutionAccessInterface::class),
            ServiceLocator::getInstance()->get(PrintInstitutionDirectoryInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveRepositoryInterface::class),
        ),
    ],
    DigitalArchiveSnapshot::class => [
        'constructor' => static fn(): DigitalArchiveSnapshot => new DigitalArchiveSnapshot(
            ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
            ServiceLocator::getInstance()->get(PurchasedDigitalLinesInterface::class),
            ServiceLocator::getInstance()->get(BoundedChildPhotosInterface::class),
            ServiceLocator::getInstance()->get(PhotoFilenamesInterface::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveLayout::class),
            ServiceLocator::getInstance()->get(DigitalArchivePolicy::class),
        ),
    ],
    DigitalArchiveView::class => [
        'constructor' => static fn(): DigitalArchiveView => new DigitalArchiveView(ServiceLocator::getInstance()->get(ClockInterface::class)),
    ],
    CreateInstitutionDigitalArchiveUseCase::class => [
        'constructor' => static fn(): CreateInstitutionDigitalArchiveUseCase => new CreateInstitutionDigitalArchiveUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(DigitalArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveSnapshot::class),
            ServiceLocator::getInstance()->get(DigitalArchiveView::class),
            ServiceLocator::getInstance()->get(DownloadIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchivePublisherInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ),
    ],
    ListInstitutionDigitalArchivesUseCase::class => [
        'constructor' => static fn(): ListInstitutionDigitalArchivesUseCase => new ListInstitutionDigitalArchivesUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(DigitalArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveView::class),
        ),
    ],
    GetInstitutionDigitalArchiveUseCase::class => [
        'constructor' => static fn(): GetInstitutionDigitalArchiveUseCase => new GetInstitutionDigitalArchiveUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(DigitalArchiveView::class),
        ),
    ],
    DownloadInstitutionDigitalArchiveUseCase::class => [
        'constructor' => static fn(): DownloadInstitutionDigitalArchiveUseCase => new DownloadInstitutionDigitalArchiveUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(DownloadTokenInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveWriterInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
        ),
    ],
    ExportInstitutionDigitalArchiveUseCase::class => [
        'constructor' => static fn(): ExportInstitutionDigitalArchiveUseCase => new ExportInstitutionDigitalArchiveUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(DigitalArchiveWriterInterface::class),
        ),
    ],
    DigitalArchiveBuild::class => [
        'constructor' => static fn(): DigitalArchiveBuild => new DigitalArchiveBuild(
            ServiceLocator::getInstance()->get(DigitalArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveAccess::class),
            ServiceLocator::getInstance()->get(OriginalFilesInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchiveWriterInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchivePolicy::class),
            ServiceLocator::getInstance()->get(DownloadIdGeneratorInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ),
    ],
    BuildInstitutionDigitalArchiveMessageHandler::class => [
        'constructor' => static fn(): BuildInstitutionDigitalArchiveMessageHandler => new BuildInstitutionDigitalArchiveMessageHandler(ServiceLocator::getInstance()->get(DigitalArchiveBuild::class)),
    ],
    MaintainInstitutionDigitalArchivesUseCase::class => [
        'constructor' => static fn(): MaintainInstitutionDigitalArchivesUseCase => new MaintainInstitutionDigitalArchivesUseCase(
            ServiceLocator::getInstance()->get(DigitalArchiveRepositoryInterface::class),
            ServiceLocator::getInstance()->get(DigitalArchivePublisherInterface::class),
            ServiceLocator::getInstance()->get(ProtectedStorageInterface::class),
            ServiceLocator::getInstance()->get(ClockInterface::class),
            Log::channel(LogChannelEnum::files),
        ),
    ],
    DigitalArchiveResultMapper::class => ['className' => DigitalArchiveResultMapper::class],
    InstitutionDigitalArchiveController::class => [
        'constructor' => static fn(): InstitutionDigitalArchiveController => new InstitutionDigitalArchiveController(
            ServiceLocator::getInstance()->get(ListInstitutionDigitalArchivesUseCase::class),
            ServiceLocator::getInstance()->get(CreateInstitutionDigitalArchiveUseCase::class),
            ServiceLocator::getInstance()->get(GetInstitutionDigitalArchiveUseCase::class),
            ServiceLocator::getInstance()->get(DownloadInstitutionDigitalArchiveUseCase::class),
            ServiceLocator::getInstance()->get(ExportInstitutionDigitalArchiveUseCase::class),
            ServiceLocator::getInstance()->get(DigitalArchiveResultMapper::class),
        ),
    ],
    InstitutionDigitalArchiveContentController::class => [
        'constructor' => static fn(): InstitutionDigitalArchiveContentController => new InstitutionDigitalArchiveContentController(ServiceLocator::getInstance()->get(DownloadInstitutionDigitalArchiveUseCase::class)),
    ],
];
