<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Archive\UseCase;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\GroupProductionStatusOutputDto;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

/**
 * PRD-01: состояние печати групп одной страницы «Ссылок и сроков» одним запросом — для значка в строке группы.
 * Группы вне области сотрудника и несуществующие просто не возвращаются.
 */
final readonly class ListProductionGroupsUseCase
{
    public function __construct(
        private ProductionAccessInterface $access,
        private GroupDirectoryInterface $groups,
        private ProductionLookup $lookup,
        private ArchiveRepositoryInterface $archives,
        private ProductionStatePolicy $states,
        private ClockInterface $clock,
    ) {}

    /**
     * @param list<string> $groupIds публичные ID групп
     *
     * @return list<GroupProductionStatusOutputDto>
     */
    public function execute(int $actorId, array $groupIds): array
    {
        $scope = $this->access->scope($actorId);
        $visible = [];
        foreach ($groupIds as $groupId) {
            $group = $this->groups->find($groupId);
            if (null !== $group && $this->lookup->visible($scope, $group->institutionNativeId)) {
                $visible[] = $group;
            }
        }
        if ([] === $visible) {
            return [];
        }
        $versions = $this->archives->byGroups(array_map(static fn($group): int => $group->nativeId, $visible));
        $since = $this->archives->cursor()->sinceAt;
        $now = $this->clock->now();
        $items = [];
        foreach ($visible as $group) {
            $groupVersions = $versions[$group->nativeId] ?? [];
            $latest = [] === $groupVersions ? null : $groupVersions[array_key_last($groupVersions)];
            $items[] = new GroupProductionStatusOutputDto(
                groupId: $group->id,
                state: $this->states->state($this->lookup->deadline($group), $latest, $since, $now)->value,
                version: $latest?->number,
            );
        }

        return $items;
    }
}
