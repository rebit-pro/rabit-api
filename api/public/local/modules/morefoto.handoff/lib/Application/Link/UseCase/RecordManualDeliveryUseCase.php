<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkTransmissionInputDto;
use Morefoto\Handoff\Application\Link\Dto\ManualDeliveryOutputDto;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отдельно фиксирует фактическую передачу ссылки в галерее с запланированным открытием.
 * Сохраняет время и сотрудника один раз, не меняя T0 и сроки продаж.
 */
final readonly class RecordManualDeliveryUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private OpeningScheduleRepositoryInterface $openings,
        private GroupLinkRepositoryInterface $links,
        private GalleryLinkInterface $galleryLinks,
        private LinkDeliveryPolicy $delivery,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, LinkTransmissionInputDto $input): ManualDeliveryOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): ManualDeliveryOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::RECORD_MANUAL_DELIVERY, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
                'sentAt' => $input->sentAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            ]);
            if (null !== $command->replay) {
                return $this->restore($command->replay);
            }
            $opening = $this->openings->find($command->group->nativeId, true);
            if (!in_array($opening?->status, [OpeningStatusEnum::SCHEDULED, OpeningStatusEnum::OPENED], true)) {
                throw new HttpException('OPENING_NOT_SCHEDULED', 409);
            }
            if (null !== $opening->manualSentAt) {
                $result = ['revision' => $command->state->revision, 'manualSentAt' => $opening->manualSentAt->format(\DateTimeInterface::ATOM)];
                $this->session->remember($command, $key, $result);

                return $this->restore($result);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            $link = $this->galleryLinks->current($groupId);
            if (null === $link || !$this->delivery->acceptsSentAt($input->sentAt, $link->issuedAt)) {
                throw new HttpException('SENT_AT_BEFORE_LINK', 422);
            }
            if ($input->sentAt > $this->clock->now()) {
                throw new HttpException('SENT_AT_IN_FUTURE', 422);
            }
            $this->openings->recordManual($command->group->nativeId, $input->sentAt, $actorId);
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $result = ['revision' => $revision, 'manualSentAt' => $input->sentAt->format(\DateTimeInterface::ATOM)];
            $this->session->remember($command, $key, $result);

            return $this->restore($result);
        });
    }

    /** @param array<string, bool|int|string> $result */
    private function restore(array $result): ManualDeliveryOutputDto
    {
        return new ManualDeliveryOutputDto((int)$result['revision'], (string)$result['manualSentAt']);
    }
}
