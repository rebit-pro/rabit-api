<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\RunGalleryOpeningsOutputDto;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

/**
 * Догоняет назначенные открытия по сохранённому времени и повторно проверяет готовность материалов.
 * Каждая группа открывается атомарно один раз; неготовый план блокируется без запуска продаж.
 */
final readonly class RunGalleryOpeningsUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private OpeningScheduleRepositoryInterface $openings,
        private GroupLinkAccessInterface $access,
        private GroupCalendarInterface $calendar,
        private GroupDirectoryInterface $directory,
        private GroupLinkRepositoryInterface $links,
        private GroupLinkReadiness $readiness,
        private LinkReadinessPolicy $policy,
        private GalleryLinkInterface $galleryLinks,
        private ClockInterface $clock,
    ) {}

    public function execute(int $limit): RunGalleryOpeningsOutputDto
    {
        $opened = 0;
        $blocked = 0;
        $skipped = 0;
        foreach ($this->openings->due($this->clock->now(), $limit) as $groupId) {
            $outcome = $this->transaction->execute(function() use ($groupId): string {
                $this->access->lockState();
                $this->calendar->lock($groupId);
                $group = $this->directory->find($groupId);
                if (null === $group) {
                    return 'skipped';
                }
                $state = $this->links->lock($group->nativeId);
                $assessment = $this->readiness->assessLocked($group);
                $schedule = $this->openings->find($group->nativeId, true);
                if (null === $schedule || OpeningStatusEnum::SCHEDULED !== $schedule->status || $schedule->openAt > $this->clock->now()) {
                    return 'skipped';
                }
                if (null !== $group->calendar->sentAt
                    || null === $this->galleryLinks->current($groupId)
                    || !$this->policy->prepared(false, $state, $assessment->readiness)) {
                    $this->openings->block($group->nativeId);

                    return 'blocked';
                }
                // Calendar and DATETIME storage use second precision.
                $openedAt = new \DateTimeImmutable('@' . $this->clock->now()->getTimestamp());
                $this->calendar->recordLinkSent(new LinkSentInputDto(
                    groupId: $groupId,
                    actorUserId: $schedule->scheduledBy,
                    operationId: Uuid::uuid4()->toString(),
                    sentAt: $openedAt,
                    reason: null,
                ));
                $this->openings->opened($group->nativeId, $openedAt);
                $this->links->advance($group->nativeId, $state->revision);

                return 'opened';
            });
            match ($outcome) {
                'opened' => ++$opened,
                'blocked' => ++$blocked,
                default => ++$skipped,
            };
        }

        return new RunGalleryOpeningsOutputDto($opened, $blocked, $skipped);
    }
}
