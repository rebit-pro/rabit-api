<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\OpeningCommandInputDto;
use Morefoto\Handoff\Application\Link\Dto\OpeningCommandOutputDto;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Назначает, переносит или отменяет автоматическое открытие уже проверенной галереи.
 * Проверяет актуальную готовность под блокировками и сохраняет команду с ключом повтора.
 */
final readonly class ManageGalleryOpeningUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private OpeningScheduleRepositoryInterface $openings,
        private GalleryLinkInterface $galleryLinks,
        private LinkReadinessPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function schedule(int $actorId, string $groupId, IdempotencyKey $key, OpeningCommandInputDto $input): OpeningCommandOutputDto
    {
        $openAt = $input->openAt ?? throw new \InvalidArgumentException('Opening moment is required.');

        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input, $openAt): OpeningCommandOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::SCHEDULE_OPENING, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
                'openAt' => $openAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            ]);
            if (null !== $command->replay) {
                return $this->restore($command->replay);
            }
            if ($openAt <= $this->clock->now()) {
                throw new HttpException('OPENING_IN_PAST', 422);
            }
            if (null !== $command->group->calendar->sentAt) {
                throw new HttpException('GALLERY_ALREADY_OPEN', 409);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            if (null === $this->galleryLinks->current($groupId)
                || !$this->policy->prepared(false, $command->state, $command->assessment->readiness)) {
                throw new HttpException('LINK_NOT_PREPARED', 409);
            }
            $existing = $this->openings->find($command->group->nativeId, true);
            if (OpeningStatusEnum::OPENED === $existing?->status) {
                throw new HttpException('GALLERY_ALREADY_OPEN', 409);
            }
            $this->openings->schedule($command->group->nativeId, $openAt, $actorId);
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $result = ['revision' => $revision, 'status' => OpeningStatusEnum::SCHEDULED->value, 'openAt' => $openAt->format(\DateTimeInterface::ATOM)];
            $this->session->remember($command, $key, $result);

            return $this->restore($result);
        });
    }

    public function cancel(int $actorId, string $groupId, IdempotencyKey $key, OpeningCommandInputDto $input): OpeningCommandOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): OpeningCommandOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::CANCEL_OPENING, $key, ['revision' => $input->revision]);
            if (null !== $command->replay) {
                return $this->restore($command->replay);
            }
            if ($input->revision !== $command->state->revision) {
                throw new HttpException('REVISION_CONFLICT', 409);
            }
            $existing = $this->openings->find($command->group->nativeId, true);
            if (null === $existing || !in_array($existing->status, [OpeningStatusEnum::SCHEDULED, OpeningStatusEnum::BLOCKED], true)) {
                throw new HttpException('OPENING_NOT_SCHEDULED', 409);
            }
            $this->openings->cancel($command->group->nativeId);
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $result = ['revision' => $revision, 'status' => OpeningStatusEnum::CANCELLED->value, 'openAt' => $existing->openAt->format(\DateTimeInterface::ATOM)];
            $this->session->remember($command, $key, $result);

            return $this->restore($result);
        });
    }

    /** @param array<string, bool|int|string> $result */
    private function restore(array $result): OpeningCommandOutputDto
    {
        return new OpeningCommandOutputDto((int)$result['revision'], (string)$result['status'], (string)$result['openAt']);
    }
}
