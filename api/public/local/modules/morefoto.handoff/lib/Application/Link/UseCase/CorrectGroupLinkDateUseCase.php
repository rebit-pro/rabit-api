<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkCalendarOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCorrectionInputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Исправляет ошибочно записанный момент передачи ссылки с причиной: Organization пересчитывает приём и доставку,
 * сохраняя согласованное продление, а история группы хранит прежние и новые сроки.
 */
final readonly class CorrectGroupLinkDateUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private GalleryLinkInterface $galleryLinks,
        private GroupCalendarInterface $calendar,
        private LinkDeliveryPolicy $delivery,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, LinkCorrectionInputDto $input): LinkCalendarOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): LinkCalendarOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::CORRECT, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
                'sentAt' => $input->sentAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
                'reason' => $input->reason,
            ]);
            if (null !== $command->replay) {
                return $this->mapper->calendar($command->replay);
            }
            $before = $command->group->calendar;
            if (null === $before->sentAt) {
                throw new HttpException('LINK_NOT_SENT', 409);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            $link = $this->galleryLinks->current($groupId);
            if (null !== $link && !$this->delivery->acceptsSentAt($input->sentAt, $link->issuedAt)) {
                throw new HttpException('SENT_AT_BEFORE_LINK', 422);
            }
            $mutation = $this->calendar->correctLinkSent(new LinkSentInputDto($groupId, $actorId, Uuid::uuid4()->toString(), $input->sentAt, $input->reason));
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $this->links->appendHistory(new LinkHistoryEntry(
                groupId: $command->group->nativeId,
                kind: LinkEventKindEnum::CORRECTED,
                actorId: $command->actor->id,
                actorName: $command->actor->name,
                sentAt: $mutation->calendar->sentAt,
                closesAt: $mutation->calendar->closesAt,
                deliveryDueAt: $mutation->calendar->deliveryDueAt,
                previousSentAt: $before->sentAt,
                previousClosesAt: $before->closesAt,
                previousDeliveryDueAt: $before->deliveryDueAt,
                reason: $input->reason,
            ));
            $result = $this->mapper->calendarResult($revision, $mutation->calendar);
            $this->session->remember($command, $key, $result);

            return $this->mapper->calendar($result);
        });
    }
}
