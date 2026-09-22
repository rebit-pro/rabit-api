<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\Service;

use Morefoto\Handoff\Application\Link\Dto\LinkAssessmentOutputDto;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkFacts;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;
use Rebit\Share\Contracts\Media\Dto\GroupMaterialsOutputDto;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

/**
 * Собирает у владельцев кадров, условий, назначений и списков сотрудников факты готовности групп к передаче ссылки.
 * Для команды берёт блокировки медиа и условий, чтобы проверенное состояние не изменилось до фиксации результата.
 */
final readonly class GroupLinkReadiness
{
    public function __construct(
        private GroupMaterialsInterface $materials,
        private GroupSalesReadinessInterface $sales,
        private GroupAccessInterface $assignments,
        private GroupLinkRepositoryInterface $links,
        private LinkReadinessPolicy $policy,
    ) {}

    /**
     * @param list<GroupDirectoryItemOutputDto> $groups
     *
     * @return array<int, LinkAssessmentOutputDto> keyed by native group ID
     */
    public function assess(array $groups): array
    {
        if ([] === $groups) {
            return [];
        }

        return $this->evaluate($groups, $this->materials->snapshots($this->ids($groups)));
    }

    public function assessLocked(GroupDirectoryItemOutputDto $group): LinkAssessmentOutputDto
    {
        return $this->evaluate([$group], [$group->nativeId => $this->materials->lock($group->shootNativeId, $group->nativeId)])[$group->nativeId];
    }

    /**
     * @param list<GroupDirectoryItemOutputDto>   $groups
     * @param array<int, GroupMaterialsOutputDto> $materials
     *
     * @return array<int, LinkAssessmentOutputDto>
     */
    private function evaluate(array $groups, array $materials): array
    {
        $ids = $this->ids($groups);
        $sales = $this->sales->readiness($ids);
        $teachers = $this->assignments->assignments($ids);
        $pending = $this->links->pendingStaffRequests($ids);
        $result = [];
        foreach ($groups as $group) {
            $media = $materials[$group->nativeId];
            $sale = $sales[$group->nativeId];
            $result[$group->nativeId] = new LinkAssessmentOutputDto(
                readiness: $this->policy->evaluate(new LinkFacts(
                    groupId: $group->id,
                    groupName: $group->name,
                    groupKind: $group->kind,
                    shootId: $group->shootId,
                    institutionId: $group->institutionId,
                    teacherId: ($teachers[$group->nativeId] ?? null)?->teacherId,
                    readyPhotos: $media->readyPhotos,
                    processingPhotos: $media->processingPhotos,
                    unassignedPhotos: $media->unassignedPhotos,
                    materialsFingerprint: $media->fingerprint,
                    activeProducts: $sale->activeProducts,
                    salesFingerprint: $sale->fingerprint,
                    pendingRequests: $pending[$group->nativeId] ?? [],
                )),
                photoCount: $media->readyPhotos,
                childCount: $media->children,
            );
        }

        return $result;
    }

    /**
     * @param list<GroupDirectoryItemOutputDto> $groups
     *
     * @return list<int>
     */
    private function ids(array $groups): array
    {
        return array_map(static fn(GroupDirectoryItemOutputDto $group): int => $group->nativeId, $groups);
    }
}
