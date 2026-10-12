<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkOutputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\MarkupCorrectionAvailability;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Commerce\GroupOrdersInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Даёт сотруднику из области группы карточку ссылки: действующий ключ галереи, подпись для команд, проблемы готовности,
 * фактические сроки, историю проверок, передач и исправлений, а также можно ли сейчас начать исправление разметки (D4).
 */
final readonly class GetGroupLinkUseCase
{
    public function __construct(
        private GroupLinkAccessInterface $access,
        private GroupDirectoryInterface $directory,
        private GroupLinkReadiness $readiness,
        private GroupLinkRepositoryInterface $links,
        private GalleryLinkInterface $galleryLinks,
        private LinkPermissionPolicy $permissions,
        private LinkReadinessPolicy $policy,
        private GroupLinkOutputMapper $mapper,
        private ClockInterface $clock,
        private GroupOrdersInterface $orders,
        private MarkupCorrectionAvailability $corrections,
    ) {}

    public function execute(int $actorId, string $groupId): GroupLinkOutputDto
    {
        $actor = $this->access->actor($actorId);
        if (!$this->permissions->allows($actor->role, LinkActionEnum::READ)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $group = $this->directory->find($groupId);
        if (null === $group || !$this->permissions->visible($actor->role, $actor->institutionIds, $actor->groupIds, $group->institutionNativeId, $group->nativeId)) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }
        $assessment = $this->readiness->assess([$group])[$group->nativeId];
        $state = $this->links->states([$group->nativeId])[$group->nativeId] ?? new LinkState();
        $calendar = $group->calendar;
        // Orders are read only when everything else already allows the correction.
        $correctable = null !== $calendar->sentAt && null === $group->markupCorrectionAt
            && $this->permissions->allows($actor->role, LinkActionEnum::START_MARKUP_CORRECTION)
            && $this->corrections->available($calendar->sentAt, $calendar->status, $group->markupCorrectionAt, $this->orders->exist($groupId));

        return $this->mapper->detail(
            group: $group,
            assessment: $assessment,
            revision: $state->revision,
            prepared: $this->policy->prepared(null !== $group->calendar->sentAt, $state, $assessment->readiness),
            galleryToken: $this->galleryLinks->current($groupId)?->token,
            now: $this->clock->now(),
            history: $this->links->history($group->nativeId),
            markupCorrectable: $correctable,
        );
    }
}
