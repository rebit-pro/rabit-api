<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkMarkupCorrectionOutputDto;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionStartInputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Commerce\GroupOrdersInterface;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionInputDto;
use Rebit\Share\Contracts\Organization\GroupMarkupCorrectionInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Начинает исправление разметки переданной группы (D4): под эксклюзивной блокировкой группы проверяет, что у неё нет
 * ни одного заказа, и ставит продажу на паузу, не меняя T0 и сроков. Заказ, созданный раньше, отклоняет старт, а
 * заказ после старта — сама пауза; событие попадает в историю ссылки.
 */
final readonly class StartMarkupCorrectionUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private GroupOrdersInterface $orders,
        private GroupMarkupCorrectionInterface $corrections,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, MarkupCorrectionStartInputDto $input): LinkMarkupCorrectionOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): LinkMarkupCorrectionOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::START_MARKUP_CORRECTION, $key, ['revision' => $input->revision]);
            if (null !== $command->replay) {
                return $this->mapper->markupCorrection($command->replay);
            }
            if (null === $command->group->calendar->sentAt) {
                throw new HttpException('LINK_NOT_SENT', 409);
            }
            if (null !== $command->group->markupCorrectionAt) {
                throw new HttpException('CORRECTION_IN_PROGRESS', 409);
            }
            if ($input->revision !== $command->state->revision) {
                throw new HttpException('REVISION_CONFLICT', 409);
            }
            // The group row is locked exclusively and an order reads it with a shared lock: no order slips in after this check.
            if ($this->orders->exist($groupId)) {
                throw new HttpException('GROUP_HAS_ORDERS', 409);
            }
            $correction = $this->corrections->start(new MarkupCorrectionInputDto($groupId, $actorId, Uuid::uuid4()->toString()));
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $this->links->appendHistory(new LinkHistoryEntry(
                groupId: $command->group->nativeId,
                kind: LinkEventKindEnum::MARKUP_STARTED,
                actorId: $command->actor->id,
                actorName: $command->actor->name,
            ));
            $result = $this->mapper->markupCorrectionResult($revision, $correction->startedAt);
            $this->session->remember($command, $key, $result);

            return $this->mapper->markupCorrection($result);
        });
    }
}
