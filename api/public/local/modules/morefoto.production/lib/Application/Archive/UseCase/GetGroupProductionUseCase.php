<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\GroupProductionOutputDto;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;

/**
 * PRD-02: блок «Печать» группы — состояние, версии со ссылками и оплаченная печать, ещё не вошедшая в архив, чтобы
 * организатор увидел поздно подтверждённые оплаты и собрал дополнение (L1-DEC-02).
 */
final readonly class GetGroupProductionUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private ProductionLookup $lookup,
        private ArchiveRepositoryInterface $archives,
        private GroupPrintOrdersInterface $orders,
        private VersionView $view,
        private ProductionStatePolicy $states,
        private ArchivePolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $groupId): GroupProductionOutputDto
    {
        $scope = $this->access->scope($actorId);
        $group = $this->lookup->group($scope, $groupId);
        $deadline = $this->lookup->deadline($group);
        $versions = $this->archives->byGroups([$group->nativeId])[$group->nativeId] ?? [];
        $now = $this->clock->now();
        $outsideLines = 0;
        $outsidePrints = 0;
        $waiting = 0;
        if (null !== $deadline && $this->states->closed($deadline, $now)) {
            $included = array_flip($this->archives->includedLines($group->nativeId, $deadline));
            foreach ($this->orders->paidPrintLines($group->id, $deadline) as $line) {
                if (!isset($included[$line->lineId])) {
                    ++$outsideLines;
                    $outsidePrints += $line->quantity * $line->printCount;
                }
            }
            $waiting = $this->orders->waitingPrintOrders($group->id);
        }

        return new GroupProductionOutputDto(
            groupId: $group->id,
            groupName: $group->name,
            state: $this->states->state($deadline, [] === $versions ? null : $versions[array_key_last($versions)], $this->archives->cursor()->sinceAt, $now)->value,
            deadlineAt: $deadline,
            buildFrom: null === $deadline ? null : $this->policy->buildFrom($deadline),
            canManage: $scope->canManage,
            outsideLines: $outsideLines,
            outsidePrints: $outsidePrints,
            waitingOrders: $waiting,
            versions: $this->view->many($versions, $now),
        );
    }
}
