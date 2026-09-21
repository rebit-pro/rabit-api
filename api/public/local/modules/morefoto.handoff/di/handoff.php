<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Application\Request\UseCase\ClarifyStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\ListStaffRequestsUseCase;
use Morefoto\Handoff\Application\Request\UseCase\SaveStaffRequestUseCase;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use Morefoto\Handoff\Infrastructure\Database\BitrixHandoffTransaction;
use Morefoto\Handoff\Presentation\Controller\StaffRequestController;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\StaffChildReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Morefoto\Handoff\Infrastructure\Gallery\StaffEligibility;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;

$services = [
    StaffEligibilityInterface::class => ['constructor' => static fn(): StaffEligibilityInterface => new StaffEligibility()],
    StaffRequestInputMapper::class => ['className' => StaffRequestInputMapper::class],
    StaffRequestResultMapper::class => ['className' => StaffRequestResultMapper::class],
    HandoffTransactionInterface::class => ['constructor' => static fn(): HandoffTransactionInterface => new BitrixHandoffTransaction()],
    StaffRequestRepository::class => ['className' => StaffRequestRepository::class],
];
$dependencies = [
    StaffRequestWorkflow::class => [HandoffTransactionInterface::class, StaffRequestRepository::class, StaffRequestAccessInterface::class, MediaScopeInterface::class, StaffChildReferenceInterface::class],
    ListStaffRequestsUseCase::class => [StaffRequestWorkflow::class],
    GetStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    SaveStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    ClarifyStaffRequestUseCase::class => [StaffRequestWorkflow::class],
    StaffRequestController::class => [ListStaffRequestsUseCase::class, GetStaffRequestUseCase::class, SaveStaffRequestUseCase::class, ClarifyStaffRequestUseCase::class, StaffRequestInputMapper::class, StaffRequestResultMapper::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
