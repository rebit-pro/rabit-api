<?php

declare(strict_types=1);

use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Link\Service\GalleryOpeningReader;
use Morefoto\Handoff\Application\Link\UseCase\CorrectGroupLinkDateUseCase;
use Morefoto\Handoff\Application\Link\UseCase\FinishMarkupCorrectionUseCase;
use Morefoto\Handoff\Application\Link\UseCase\GetGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\ListGroupLinksUseCase;
use Morefoto\Handoff\Application\Link\UseCase\PrepareGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\StartMarkupCorrectionUseCase;
use Morefoto\Handoff\Application\Link\UseCase\TransmitGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\ManageGalleryOpeningUseCase;
use Morefoto\Handoff\Application\Link\UseCase\RunGalleryOpeningsUseCase;
use Morefoto\Handoff\Application\Link\UseCase\RecordManualDeliveryUseCase;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\Service\MarkupCorrectionAvailability;
use Morefoto\Handoff\Infrastructure\Database\BitrixGroupLinkRepository;
use Morefoto\Handoff\Infrastructure\Database\BitrixOpeningScheduleRepository;
use Morefoto\Handoff\Infrastructure\Database\BitrixHandoffTransaction;
use Morefoto\Handoff\Presentation\Controller\GroupLinkController;
use Morefoto\Handoff\Presentation\Controller\GalleryOpeningController;
use Morefoto\Handoff\Presentation\Command\RunGalleryOpeningsCommand;
use Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper;
use Morefoto\Handoff\Presentation\Link\GroupLinkResultMapper;
use Morefoto\Handoff\Presentation\Link\OpeningInputMapper;
use Morefoto\Handoff\Presentation\Link\OpeningResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Commerce\GroupOrdersInterface;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;
use Rebit\Share\Contracts\Handoff\GalleryOpeningInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\GroupMarkupCorrectionInterface;

$services = [
    GroupLinkRepositoryInterface::class => ['constructor' => static fn(): GroupLinkRepositoryInterface => new BitrixGroupLinkRepository()],
    OpeningScheduleRepositoryInterface::class => ['constructor' => static fn(): OpeningScheduleRepositoryInterface => new BitrixOpeningScheduleRepository()],
    GalleryOpeningInterface::class => ['constructor' => static fn(): GalleryOpeningInterface => new GalleryOpeningReader(ServiceLocator::getInstance()->get(OpeningScheduleRepositoryInterface::class))],
    LinkReadinessPolicy::class => ['className' => LinkReadinessPolicy::class],
    LinkPermissionPolicy::class => ['className' => LinkPermissionPolicy::class],
    LinkDeliveryPolicy::class => ['className' => LinkDeliveryPolicy::class],
    MarkupCorrectionAvailability::class => ['className' => MarkupCorrectionAvailability::class],
    GroupLinkOutputMapper::class => ['className' => GroupLinkOutputMapper::class],
    GroupLinkInputMapper::class => ['className' => GroupLinkInputMapper::class],
    GroupLinkResultMapper::class => ['className' => GroupLinkResultMapper::class],
    OpeningInputMapper::class => ['className' => OpeningInputMapper::class],
    OpeningResultMapper::class => ['className' => OpeningResultMapper::class],
];
$dependencies = [
    GroupLinkReadiness::class => [GroupMaterialsInterface::class, GroupSalesReadinessInterface::class, GroupAccessInterface::class, GroupLinkRepositoryInterface::class, LinkReadinessPolicy::class],
    GroupLinkCommandSession::class => [GroupLinkAccessInterface::class, GroupCalendarInterface::class, GroupDirectoryInterface::class, GroupLinkRepositoryInterface::class, GroupLinkReadiness::class, LinkPermissionPolicy::class],
    ListGroupLinksUseCase::class => [GroupLinkAccessInterface::class, GroupDirectoryInterface::class, GroupLinkReadiness::class, GroupLinkRepositoryInterface::class, LinkPermissionPolicy::class, LinkReadinessPolicy::class, GroupLinkOutputMapper::class, InstitutionAccessInterface::class, OpeningScheduleRepositoryInterface::class],
    GetGroupLinkUseCase::class => [GroupLinkAccessInterface::class, GroupDirectoryInterface::class, GroupLinkReadiness::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, LinkPermissionPolicy::class, LinkReadinessPolicy::class, GroupLinkOutputMapper::class, ClockInterface::class, GroupOrdersInterface::class, MarkupCorrectionAvailability::class, OpeningScheduleRepositoryInterface::class],
    PrepareGroupLinkUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupLinkOutputMapper::class],
    TransmitGroupLinkUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupCalendarInterface::class, LinkReadinessPolicy::class, LinkDeliveryPolicy::class, GroupLinkOutputMapper::class, OpeningScheduleRepositoryInterface::class],
    CorrectGroupLinkDateUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupCalendarInterface::class, LinkDeliveryPolicy::class, GroupLinkOutputMapper::class, OpeningScheduleRepositoryInterface::class],
    GroupLinkController::class => [ListGroupLinksUseCase::class, GetGroupLinkUseCase::class, PrepareGroupLinkUseCase::class, TransmitGroupLinkUseCase::class, CorrectGroupLinkDateUseCase::class, StartMarkupCorrectionUseCase::class, FinishMarkupCorrectionUseCase::class, GroupLinkInputMapper::class, GroupLinkResultMapper::class],
    GalleryOpeningController::class => [ManageGalleryOpeningUseCase::class, OpeningInputMapper::class, OpeningResultMapper::class, RecordManualDeliveryUseCase::class, GroupLinkInputMapper::class],
    RecordManualDeliveryUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, OpeningScheduleRepositoryInterface::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, LinkDeliveryPolicy::class, ClockInterface::class],
    RunGalleryOpeningsCommand::class => [RunGalleryOpeningsUseCase::class],
];
$services[ManageGalleryOpeningUseCase::class] = [
    'className' => ManageGalleryOpeningUseCase::class,
    'constructorParams' => static fn(): array => [
        new BitrixHandoffTransaction(3),
        ServiceLocator::getInstance()->get(GroupLinkCommandSession::class),
        ServiceLocator::getInstance()->get(GroupLinkRepositoryInterface::class),
        ServiceLocator::getInstance()->get(OpeningScheduleRepositoryInterface::class),
        ServiceLocator::getInstance()->get(GalleryLinkInterface::class),
        ServiceLocator::getInstance()->get(LinkReadinessPolicy::class),
        ServiceLocator::getInstance()->get(ClockInterface::class),
    ],
];
$services[RunGalleryOpeningsUseCase::class] = [
    'className' => RunGalleryOpeningsUseCase::class,
    'constructorParams' => static fn(): array => [
        new BitrixHandoffTransaction(3),
        ServiceLocator::getInstance()->get(OpeningScheduleRepositoryInterface::class),
        ServiceLocator::getInstance()->get(GroupLinkAccessInterface::class),
        ServiceLocator::getInstance()->get(GroupCalendarInterface::class),
        ServiceLocator::getInstance()->get(GroupDirectoryInterface::class),
        ServiceLocator::getInstance()->get(GroupLinkRepositoryInterface::class),
        ServiceLocator::getInstance()->get(GroupLinkReadiness::class),
        ServiceLocator::getInstance()->get(LinkReadinessPolicy::class),
        ServiceLocator::getInstance()->get(GalleryLinkInterface::class),
        ServiceLocator::getInstance()->get(ClockInterface::class),
    ],
];
// D4: an order holds the group row with a shared lock while the start locks it exclusively; a deadlock repeats the
// whole re-reading command, as for the D3 transfer.
$services[StartMarkupCorrectionUseCase::class] = [
    'className' => StartMarkupCorrectionUseCase::class,
    'constructorParams' => static fn(): array => [
        new BitrixHandoffTransaction(3),
        ServiceLocator::getInstance()->get(GroupLinkCommandSession::class),
        ServiceLocator::getInstance()->get(GroupLinkRepositoryInterface::class),
        ServiceLocator::getInstance()->get(GroupOrdersInterface::class),
        ServiceLocator::getInstance()->get(GroupMarkupCorrectionInterface::class),
        ServiceLocator::getInstance()->get(GroupLinkOutputMapper::class),
    ],
];
$services[FinishMarkupCorrectionUseCase::class] = [
    'className' => FinishMarkupCorrectionUseCase::class,
    'constructorParams' => static fn(): array => [
        new BitrixHandoffTransaction(3),
        ServiceLocator::getInstance()->get(GroupLinkCommandSession::class),
        ServiceLocator::getInstance()->get(GroupLinkRepositoryInterface::class),
        ServiceLocator::getInstance()->get(GroupMarkupCorrectionInterface::class),
        ServiceLocator::getInstance()->get(GroupLinkOutputMapper::class),
    ],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
