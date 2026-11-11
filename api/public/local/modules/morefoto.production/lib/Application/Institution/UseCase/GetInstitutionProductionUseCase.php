<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Institution\Dto\InstitutionProductionOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveAccess;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveView;
use Morefoto\Production\Application\Institution\Service\InstitutionGroups;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;

/**
 * Показывает полный архив учреждения и готовность следующего состава, сохраняя доступ к прежним выпускам.
 * Отдельно считает своевременные оплаты, ещё не вошедшие в L1, и ожидающие платежи без блокировки архива.
 */
final readonly class GetInstitutionProductionUseCase
{
    public function __construct(
        private InstitutionArchiveAccess $access,
        private InstitutionGroups $groups,
        private InstitutionArchiveRepositoryInterface $archives,
        private ArchiveRepositoryInterface $jobs,
        private GroupPrintOrdersInterface $orders,
        private InstitutionArchiveView $view,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $institutionId): InstitutionProductionOutputDto
    {
        $institution = $this->access->institution($actorId, $institutionId);
        $groups = $this->groups->all($institutionId);
        $now = $this->clock->now();
        $blockers = $this->groups->blockers($groups, $now);
        $from = $this->groups->buildFrom($groups);
        $history = $this->archives->history($institution->nativeId);
        $versions = [];
        $latestFull = null;
        foreach ($history as $archive) {
            $version = $this->view->one($archive);
            $versions[] = $version;
            if ('full' === $version->kind) {
                $latestFull = $version;
            }
        }
        $outside = 0;
        $waiting = 0;
        foreach ($groups as $group) {
            $deadline = $this->groups->deadline($group);
            if (null !== $deadline && $deadline <= $now) {
                $included = array_flip($this->jobs->includedLines($group->nativeId, $deadline));
                foreach ($this->orders->paidPrintLines($group->id, $deadline) as $line) {
                    if (!isset($included[$line->lineId])) {
                        ++$outside;
                    }
                }
                $waiting += $this->orders->waitingPrintOrders($group->id);
            }
        }
        $error = $this->archives->planningError($institution->nativeId);
        $state = match (true) {
            [] === $groups => 'empty',
            [] !== $blockers => 'open',
            null !== $from && $from > $now => 'scheduled',
            null !== $error => 'failed',
            default => $latestFull->status ?? 'scheduled',
        };

        return new InstitutionProductionOutputDto(
            institutionId: $institution->id,
            institutionName: $institution->name,
            state: $state,
            canManage: $this->access->canManage($actorId),
            buildFrom: $from,
            outsideLines: $outside,
            waitingOrders: $waiting,
            error: $error,
            blockers: $blockers,
            versions: $versions,
        );
    }
}
