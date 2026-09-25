<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Max\UseCase;

use Morefoto\Support\Application\Max\Dto\MaxUpdateInputDto;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Domain\Question\Repository\MaxChatRepositoryInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/**
 * Принимает событие MAX: запоминает групповой чат, а ответ куратора через «Ответить» на сообщение бота
 * добавляет в исходную беседу ровно один раз. Прочие сообщения группы и событий не меняют историю.
 */
final readonly class HandleMaxUpdateUseCase
{
    public function __construct(
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private MaxChatRepositoryInterface $chats,
        private QuestionTextPolicy $policy,
        private ClockInterface $clock,
        private int $chatId,
    ) {}

    public function execute(MaxUpdateInputDto $update): void
    {
        $now = $this->clock->now();
        $this->transaction->execute(function() use ($update, $now): void {
            $membership = in_array($update->updateType, ['bot_added', 'bot_removed'], true);
            if (null !== $update->chatId && ($membership || 'chat' === $update->chatType)) {
                $this->chats->seen($update->chatId, $update->updateType, 'bot_removed' !== $update->updateType, $now);
            }
            if (!$this->isCuratorReply($update)) {
                return;
            }
            $text = $this->policy->curatorReply($update->text);
            $questionId = $this->questions->questionByOutgoingMid((string)$update->replyToMid);
            if (null === $text || null === $questionId) {
                return;
            }
            $this->questions->addCuratorReply($questionId, $update->senderName, $text, (string)$update->mid, $now);
        });
    }

    private function isCuratorReply(MaxUpdateInputDto $update): bool
    {
        return 'message_created' === $update->updateType
            && 0 !== $this->chatId
            && $this->chatId === $update->chatId
            && !$update->senderIsBot
            && null !== $update->mid
            && null !== $update->replyToMid;
    }
}
