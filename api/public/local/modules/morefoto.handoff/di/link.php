<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Link\UseCase\CorrectGroupLinkDateUseCase;
use Morefoto\Handoff\Application\Link\UseCase\GetGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\ListGroupLinksUseCase;
use Morefoto\Handoff\Application\Link\UseCase\PrepareGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\TransmitGroupLinkUseCase;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Infrastructure\Database\BitrixGroupLinkRepository;
use Morefoto\Handoff\Presentation\Controller\GroupLinkController;
use Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper;
use Morefoto\Handoff\Presentation\Link\GroupLinkResultMapper;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

$services = [
    GroupLinkRepositoryInterface::class => ['constructor' => static fn(): GroupLinkRepositoryInterface => new BitrixGroupLinkRepository()],
    LinkReadinessPolicy::class => ['className' => LinkReadinessPolicy::class],
    LinkPermissionPolicy::class => ['className' => LinkPermissionPolicy::class],
    LinkDeliveryPolicy::class => ['className' => LinkDeliveryPolicy::class],
    GroupLinkOutputMapper::class => ['className' => GroupLinkOutputMapper::class],
    GroupLinkInputMapper::class => ['className' => GroupLinkInputMapper::class],
    GroupLinkResultMapper::class => ['className' => GroupLinkResultMapper::class],
];
$dependencies = [
    GroupLinkReadiness::class => [GroupMaterialsInterface::class, GroupSalesReadinessInterface::class, GroupAccessInterface::class, GroupLinkRepositoryInterface::class, LinkReadinessPolicy::class],
    GroupLinkCommandSession::class => [GroupLinkAccessInterface::class, GroupCalendarInterface::class, GroupDirectoryInterface::class, GroupLinkRepositoryInterface::class, GroupLinkReadiness::class, LinkPermissionPolicy::class],
    ListGroupLinksUseCase::class => [GroupLinkAccessInterface::class, GroupDirectoryInterface::class, GroupLinkReadiness::class, GroupLinkRepositoryInterface::class, LinkPermissionPolicy::class, LinkReadinessPolicy::class, GroupLinkOutputMapper::class],
    GetGroupLinkUseCase::class => [GroupLinkAccessInterface::class, GroupDirectoryInterface::class, GroupLinkReadiness::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, LinkPermissionPolicy::class, LinkReadinessPolicy::class, GroupLinkOutputMapper::class, ClockInterface::class],
    PrepareGroupLinkUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupLinkOutputMapper::class],
    TransmitGroupLinkUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupCalendarInterface::class, LinkReadinessPolicy::class, LinkDeliveryPolicy::class, GroupLinkOutputMapper::class],
    CorrectGroupLinkDateUseCase::class => [HandoffTransactionInterface::class, GroupLinkCommandSession::class, GroupLinkRepositoryInterface::class, GalleryLinkInterface::class, GroupCalendarInterface::class, LinkDeliveryPolicy::class, GroupLinkOutputMapper::class],
    GroupLinkController::class => [ListGroupLinksUseCase::class, GetGroupLinkUseCase::class, PrepareGroupLinkUseCase::class, TransmitGroupLinkUseCase::class, CorrectGroupLinkDateUseCase::class, GroupLinkInputMapper::class, GroupLinkResultMapper::class],
];
foreach ($dependencies as $class => $arguments) {
    $services[$class] = [
        'className' => $class,
        'constructorParams' => static fn(): array => array_map(static fn(string $dependency): object => ServiceLocator::getInstance()->get($dependency), $arguments),
    ];
}

return $services;
