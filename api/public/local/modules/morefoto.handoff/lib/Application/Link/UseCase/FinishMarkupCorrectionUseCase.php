<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkMarkupCorrectionOutputDto;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionFinishInputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionInputDto;
use Rebit\Share\Contracts\Organization\GroupMarkupCorrectionInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Завершает исправление разметки (D4) и возобновляет продажу: материалы группы должны быть готовы так же, как перед
 * передачей ссылки, а подпись — совпадать с проверенными сотрудником кадрами. Блокировка материалов не даёт правке
 * разметки проскочить между проверкой и снятием паузы; подпись опубликованных кадров попадает в историю ссылки.
 */
final readonly class FinishMarkupCorrectionUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private GroupMarkupCorrectionInterface $corrections,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, MarkupCorrectionFinishInputDto $input): LinkMarkupCorrectionOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): LinkMarkupCorrectionOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::FINISH_MARKUP_CORRECTION, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
            ]);
            if (null !== $command->replay) {
                return $this->mapper->markupCorrection($command->replay);
            }
            if (null === $command->group->markupCorrectionAt) {
                throw new HttpException('CORRECTION_NOT_STARTED', 409);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            if ([] !== $command->assessment->readiness->problems) {
                throw new HttpException('LINK_NOT_READY', 409);
            }
            $this->corrections->finish(new MarkupCorrectionInputDto($groupId, $actorId, Uuid::uuid4()->toString()));
            $revision = $this->links->advance($command->group->nativeId, $command->state->revision);
            $this->links->appendHistory(new LinkHistoryEntry(
                groupId: $command->group->nativeId,
                kind: LinkEventKindEnum::MARKUP_FINISHED,
                actorId: $command->actor->id,
                actorName: $command->actor->name,
                signature: $command->assessment->readiness->signature,
            ));
            $result = $this->mapper->markupCorrectionResult($revision, null);
            $this->session->remember($command, $key, $result);

            return $this->mapper->markupCorrection($result);
        });
    }
}
