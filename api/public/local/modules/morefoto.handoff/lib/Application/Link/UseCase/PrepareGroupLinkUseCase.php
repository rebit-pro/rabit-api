<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Link\UseCase;

use Morefoto\Handoff\Application\Link\Dto\LinkPreparationInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkPreparationOutputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Фиксирует проверку организатором актуальных кадров, условий и списков группы и выдаёт ссылку галереи для передачи.
 * Проверка привязана к серверной подписи состояния: любые проблемы или изменения после чтения формы её отклоняют.
 */
final readonly class PrepareGroupLinkUseCase
{
    public function __construct(
        private HandoffTransactionInterface $transaction,
        private GroupLinkCommandSession $session,
        private GroupLinkRepositoryInterface $links,
        private GalleryLinkInterface $galleryLinks,
        private GroupLinkOutputMapper $mapper,
    ) {}

    public function execute(int $actorId, string $groupId, IdempotencyKey $key, LinkPreparationInputDto $input): LinkPreparationOutputDto
    {
        return $this->transaction->execute(function() use ($actorId, $groupId, $key, $input): LinkPreparationOutputDto {
            $command = $this->session->open($actorId, $groupId, LinkActionEnum::PREPARE, $key, [
                'revision' => $input->revision,
                'signature' => $input->signature,
            ]);
            if (null !== $command->replay) {
                return $this->mapper->preparation($command->replay);
            }
            if (null !== $command->group->calendar->sentAt) {
                throw new HttpException('LINK_ALREADY_SENT', 409);
            }
            $this->session->assertCurrent($command, $input->revision, $input->signature);
            if ([] !== $command->assessment->readiness->problems) {
                throw new HttpException('LINK_NOT_READY', 409);
            }
            $this->galleryLinks->ensure($groupId);
            $revision = $this->links->prepare($command->group->nativeId, $command->state->revision, $input->signature, $command->actor->id);
            $this->links->appendHistory(new LinkHistoryEntry(
                groupId: $command->group->nativeId,
                kind: LinkEventKindEnum::PREPARED,
                actorId: $command->actor->id,
                actorName: $command->actor->name,
                signature: $input->signature,
            ));
            $result = $this->mapper->preparationResult($revision, $input->signature);
            $this->session->remember($command, $key, $result);

            return $this->mapper->preparation($result);
        });
    }
}
