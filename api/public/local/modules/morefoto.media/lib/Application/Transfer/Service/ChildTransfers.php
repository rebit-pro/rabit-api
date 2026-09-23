<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Transfer\Service;

use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Transfer\Repository\ChildTransferRepository;
use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use Rebit\Share\Contracts\Media\ChildTransferInterface;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetPhotoOutputDto;

/**
 * Переносит полные наборы детей между группами одной съёмки внутри транзакции вызывающего.
 * Сохраняет ID кадров, детей и связей и originalGroupId, заменяет перенесённые обложки и повышает ревизию медиа,
 * поэтому подписи подготовки ссылок затронутых групп меняются без отдельного уведомления.
 */
final readonly class ChildTransfers implements ChildTransferInterface
{
    public function __construct(
        private MediaMutationRepository $media,
        private ChildTransferRepository $transfers,
        private ChildTransferPolicy $policy,
    ) {}

    public function sets(int $shootId, array $childIds): array
    {
        return $this->read($shootId, $childIds, false);
    }

    public function lockSets(int $shootId, array $childIds): array
    {
        $this->media->lockRevision($shootId);

        return $this->read($shootId, $childIds, true);
    }

    public function freeCodes(int $groupId, int $count): array
    {
        return $this->policy->freeCodes($this->transfers->codes($groupId), $count);
    }

    public function move(int $shootId, array $moves): int
    {
        $current = $this->media->lockRevision($shootId);
        $childIds = array_map(static fn(ChildMoveInputDto $move): int => $move->childId, $moves);
        $children = $this->transfers->children($shootId, $childIds, true);
        $photosByChild = [];
        $readyByChild = [];
        foreach ($this->transfers->assignments($childIds) as $assignment) {
            $photosByChild[$assignment['childId']][] = $assignment['photoId'];
            if ('ready' === $assignment['status']) {
                $readyByChild[$assignment['childId']][] = $assignment['photoId'];
            }
        }
        /** @var array<int, int> $photoTargets photo ID => target group ID */
        $photoTargets = [];
        /** @var array<int, array<int, true>> $sourcePhotos source group ID => moved photo IDs */
        $sourcePhotos = [];
        /** @var array<int, ?int> $targetCovers target group ID => first moved ready photo */
        $targetCovers = [];
        foreach ($moves as $move) {
            $child = $children[$move->childId] ?? throw new MediaStorageException('The transferred child is missing.');
            if ($child['groupId'] === $move->targetGroupId || !$this->policy->isCode($move->targetCode)) {
                throw new \InvalidArgumentException('A transfer needs another group and a valid child code.');
            }
            $this->transfers->moveChild($move->childId, $move->targetGroupId, $move->targetCode);
            foreach ($photosByChild[$move->childId] ?? [] as $photoId) {
                if ($move->targetGroupId !== ($photoTargets[$photoId] ??= $move->targetGroupId)) {
                    throw new \InvalidArgumentException('A shared photo cannot move to two groups.');
                }
                $sourcePhotos[$child['groupId']][$photoId] = true;
            }
            $targetCovers[$move->targetGroupId] ??= $readyByChild[$move->childId][0] ?? null;
        }
        $byTarget = [];
        foreach ($photoTargets as $photoId => $groupId) {
            $byTarget[$groupId][] = $photoId;
        }
        foreach ($byTarget as $groupId => $photoIds) {
            $this->transfers->movePhotos($shootId, $photoIds, $groupId);
        }
        foreach ($sourcePhotos as $groupId => $moved) {
            $cover = $this->transfers->cover($groupId);
            if (null !== $cover && isset($moved[$cover])) {
                $this->transfers->replaceCover($groupId, $this->transfers->coverCandidate($groupId));
            }
        }
        foreach ($targetCovers as $groupId => $photoId) {
            if (null !== $photoId && null === $this->transfers->cover($groupId)) {
                $this->transfers->replaceCover($groupId, $photoId);
            }
        }

        return $this->media->advanceRevision($shootId, $current);
    }

    /**
     * @param list<int> $childIds
     *
     * @return array<int, ChildSetOutputDto>
     */
    private function read(int $shootId, array $childIds, bool $lock): array
    {
        $ids = array_values(array_unique(array_filter($childIds, static fn(int $id): bool => 0 < $id)));
        if ([] === $ids) {
            return [];
        }
        $children = $this->transfers->children($shootId, $ids, $lock);
        if ([] === $children) {
            return [];
        }
        $assignments = $this->transfers->assignments(array_keys($children));
        $photoIds = array_values(array_unique(array_column($assignments, 'photoId')));
        $shared = [] === $photoIds
            ? []
            : array_fill_keys($this->policy->sharedPhotos($this->transfers->photoChildren($photoIds), array_fill_keys(array_keys($children), true)), true);
        $photos = [];
        $sharedCodes = [];
        foreach ($assignments as $assignment) {
            $childId = $assignment['childId'];
            $code = $this->policy->frameCode($children[$childId]['code'], $assignment['sequence']);
            $photos[$childId][] = new ChildSetPhotoOutputDto($assignment['photoId'], $assignment['publicId'], $code, $assignment['revision'], $assignment['status']);
            if (isset($shared[$assignment['photoId']])) {
                $sharedCodes[$childId][] = $code;
            }
        }
        $sets = [];
        foreach ($children as $id => $child) {
            $sets[$id] = new ChildSetOutputDto($id, $child['publicId'], $child['groupId'], $child['code'], $photos[$id] ?? [], $sharedCodes[$id] ?? []);
        }

        return $sets;
    }
}
