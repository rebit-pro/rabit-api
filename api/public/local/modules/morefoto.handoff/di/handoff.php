<?php

declare(strict_types=1);

use Morefoto\Handoff\Infrastructure\Organization\StructureHandoffRemoval;
use Rebit\Share\Contracts\Handoff\StructureHandoffRemovalInterface;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Application\Request\Service\StaffTransferGuard;
use Morefoto\Handoff\Application\Request\Service\StaffTransferPlanner;
use Morefoto\Handoff\Application\Request\UseCase\ClarifyStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\ConfirmStaffTransferUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffTransferPreviewUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\ListStaffRequestsUseCase;
use Morefoto\Handoff\Application\Request\UseCase\SaveStaffRequestUseCase;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Domain\Request\Service\StaffTransferPolicy;
use Morefoto\Handoff\Infrastructure\Database\BitrixHandoffTransaction;
use Morefoto\Handoff\Presentation\Controller\StaffRequestController;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Morefoto\Handoff\Infrastructure\Gallery\StaffEligibility;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;

$services = [
    StaffEligibilityInterface::class => ['constructor' => static fn(): StaffEligibilityInterface => new StaffEligibility()],
    StructureHandoffRemovalInterface::class => ['constructor' => static fn(): StructureHandoffRemovalInterface => new StructureHandoffRemoval()],
    StaffRequestInputMapper::class => ['className' => StaffRequestInputMapper::class],
    StaffRequestResultMapper::class => ['className' => StaffRequestResultMapper::class],
    HandoffTransactionInterface::class => ['constructor' => static fn(): HandoffTransactionInterface => new BitrixHandoffTransaction()],
    StaffRequestRepository::class => ['className' => StaffRequestRepository::class],
    StaffTransferPolicy::class => ['className' => StaffTransferPolicy::class],
    // Transfers wait on Media and Commerce rows; a deadlock repeats the whole re-reading command.
    ConfirmStaffTransferUseCase::class => [
        'className' => ConfirmStaffTransferUseCase::class,
        'constructorParams' => static fn(): array => [
            new BitrixHandoffTransaction(3),
            ServiceLocator::getInstance()->get(GroupLinkAccessInterface::class),
            ServiceLocator::getInstance()->get(GroupCalendarInterface::class),
            ServiceLocator::getInstance()->get(StaffTransferGuard::class),
            ServiceLocator::getInstance()->get(StaffRequestRepository::class),
            ServiceLocator::getInstance()->get(StaffTransferPlanner::class),
            ServiceLocator::getInstance()->get(ChildTransferInterface::class),
        ],
    ],
];
$dependencies = [
    StaffRequestWorkflow::class => [HandoffTransactionInterface::class, StaffRequestRepository::class, StaffRequestAccessInterface::class, MediaScopeInterface::class, StaffChildReferenceInterface::class],
    ListStaffRequestsUseCase::class => [StaffRequestWorkflow::class],
    GetStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    SaveStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    ClarifyStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    StaffTransferGuard::class => [GroupLinkAccessInterface::class],
    // Commerce includes Handoff; the orders contract is resolved lazily after init.php loaded both modules.
    StaffTransferPlanner::class => [StaffRequestRepository::class, MediaScopeInterface::class, GroupDirectoryInterface::class, ChildTransferInterface::class, ChildOrdersInterface::class, StaffTransferPolicy::class],
    GetStaffTransferPreviewUseCase::class => [StaffTransferGuard::class, StaffRequestRepository::class, StaffTransferPlanner::class],
    StaffRequestController::class => [
        ListStaffRequestsUseCase::class,
        GetStaffRequestUseCase::class,
        SaveStaffRequestUseCase::class,
        ClarifyStaffRequestUseCase::class,
        GetStaffTransferPreviewUseCase::class,
        ConfirmStaffTransferUseCase::class,
        StaffRequestInputMapper::class,
        StaffRequestResultMapper::class,
    ],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
