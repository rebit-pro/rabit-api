<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkCalendarOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkTransmissionInputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Записывает фактический момент ручной передачи ссылки родителям и через Organization открывает приём заказов на семь дней.
 * Передачу принимает только актуальная проверка; повтор после записанного факта не сдвигает сроки и не дописывает историю.
 */
final readonly class TransmitGroupLinkUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private GalleryLinkInterface $galleryLinks,
        private GroupCalendarInterface $calendar,
        private LinkReadinessPolicy $policy,
        private LinkDeliveryPolicy $delivery,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, LinkTransmissionInputDto $input): LinkCalendarOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): LinkCalendarOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::TRANSMIT, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
                'sentAt' => $input->sentAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            ]);
            if (null !== $command->replay) {
                return $this->mapper->calendar($command->replay);
            }
            $calendar = $command->group->calendar;
            if (null !== $calendar->sentAt) {
                // A repeated report of an already recorded delivery is harmless even from a stale form (D10).
                $result = $this->mapper->calendarResult($command->state->revision, $calendar);
                $this->session->remember($command, $key, $result);

                return $this->mapper->calendar($result);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            $link = $this->galleryLinks->current($groupId);
            if (null === $link || !$this->policy->prepared(false, $command->state, $command->assessment->readiness)) {
                throw new HttpException('LINK_NOT_PREPARED', 409);
            }
            if (!$this->delivery->acceptsSentAt($input->sentAt, $link->issuedAt)) {
                throw new HttpException('SENT_AT_BEFORE_LINK', 422);
            }
            $mutation = $this->calendar->recordLinkSent(new LinkSentInputDto($groupId, $actorId, Uuid::uuid4()->toString(), $input->sentAt, null));
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $this->appendHistory($command->group->nativeId, $command->actor->id, $command->actor->name, $mutation->calendar);
            $result = $this->mapper->calendarResult($revision, $mutation->calendar);
            $this->session->remember($command, $key, $result);

            return $this->mapper->calendar($result);
        });
    }

    private function appendHistory(int $groupId, int $actorId, string $actorName, GroupCalendarOutputDto $calendar): void
    {
        $this->links->appendHistory(new LinkHistoryEntry(
            groupId: $groupId,
            kind: LinkEventKindEnum::TRANSMITTED,
            actorId: $actorId,
            actorName: $actorName,
            sentAt: $calendar->sentAt,
            closesAt: $calendar->closesAt,
            deliveryDueAt: $calendar->deliveryDueAt,
        ));
    }
}
