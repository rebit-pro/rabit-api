<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\ShootGalleryLinkInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Даёт сотруднику с доступом ко всей съёмке её общий адрес. Организатор может впервые получить адрес
 * после подтверждения готовности хотя бы одной обычной группы, сохранив индивидуальные сроки открытия.
 */
final readonly class ShootGalleryLinkUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkAccessInterface $access,
        private MediaScopeInterface $scopes,
        private LinkPermissionPolicy $permissions,
        private GroupDirectoryInterface $directory,
        private GroupLinkReadiness $readiness,
        private GroupLinkRepositoryInterface $states,
        private LinkReadinessPolicy $policy,
        private ShootGalleryLinkInterface $links,
    ) {}

    public function execute(int $actorId, string $shootId, bool $issue): string
    {
        return $this->transaction->execute(function() use ($actorId, $shootId, $issue): string {
            $this->access->lockState();
            $actor = $this->access->lockActor($actorId);
            $action = $issue ? LinkActionEnum::PREPARE : LinkActionEnum::READ;
            if (!$this->permissions->allows($actor->role, $action) || 'teacher' === $actor->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $shoot = $this->scopes->resolve($shootId);
            if (!$this->permissions->visible($actor->role, $actor->institutionIds, $actor->groupIds, $shoot->institutionId, 0)) {
                throw new HttpException('SHOOT_NOT_FOUND', 404);
            }
            if (!$issue) {
                return $this->links->current($shootId) ?? throw new HttpException('SHOOT_LINK_NOT_FOUND', 404);
            }
            $prepared = false;
            $published = [];
            $number = 1;
            do {
                $page = $this->directory->page(new GroupDirectoryQueryInputDto(null, null, null, $shootId, null, $number, 100));
                $pending = [];
                foreach ($page->items as $group) {
                    if ('regular' !== $group->kind) {
                        continue;
                    }
                    if (null !== $group->calendar->sentAt) {
                        $prepared = true;
                        $published[] = $group->id;
                    } else {
                        $pending[] = $group;
                    }
                }
                if (!$prepared && [] !== $pending) {
                    $prepared = $this->hasPreparedGroup($pending);
                }
            } while (100 * $number++ < $page->total);
            if (!$prepared) {
                throw new HttpException('LINK_NOT_PREPARED', 409);
            }

            return $this->links->ensure($shootId, $published);
        });
    }

    /** @param non-empty-list<GroupDirectoryItemOutputDto> $groups */
    private function hasPreparedGroup(array $groups): bool
    {
        $states = $this->states->states(array_column($groups, 'nativeId'));
        $assessments = $this->readiness->assess($groups);
        foreach ($groups as $group) {
            if ($this->policy->prepared(false, $states[$group->nativeId] ?? new LinkState(), $assessments[$group->nativeId]->readiness)) {
                return true;
            }
        }

        return false;
    }
}
