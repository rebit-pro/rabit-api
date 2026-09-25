<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Notification\Dto\MaxChatMessageInputDto;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;
use Rebit\Share\Application\Contract\Notification\MaxChatMessengerInterface;

/**
 * Доставляет реплики одной беседы в группу кураторов MAX строго по порядку и запоминает mid для ответов куратора.
 * Сетевой вызов идёт вне транзакции; безопасные сбои повторяются с растущей паузой, неизвестный исход не повторяется,
 * а без настроенных бота и группы реплики ждут, не расходуя попытки.
 */
final readonly class DeliverQuestionMessageUseCase
{
    public const int MAX_ATTEMPTS = 10;
    public const int LEASE_SECONDS = 300;
    private const int INITIAL_RETRY_SECONDS = 30;
    private const int MAX_RETRY_SECONDS = 3600;

    public function __construct(
        private SupportTransactionInterface $transaction,
        private QuestionDeliveryRepositoryInterface $deliveries,
        private MaxChatMessengerInterface $max,
        private MaxQuestionTextBuilder $texts,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
        private int $chatId,
    ) {}

    public function execute(int $messageId): void
    {
        if (0 === $this->chatId || !$this->max->isConfigured()) {
            // The bot or the group is not configured yet: the reply stays pending and the dispatcher offers it again later.
            return;
        }
        $now = $this->clock->now();
        $claim = $this->transaction->execute(fn(): ?array => $this->deliveries->claim($messageId, $now, $now->modify('-' . self::LEASE_SECONDS . ' seconds')));
        if (null === $claim) {
            return;
        }
        $outcome = $this->max->send(new MaxChatMessageInputDto(
            $this->chatId,
            $this->texts->text($claim['questionId'], $claim['questionAuthor'], $claim['authorName'], $claim['context'], $claim['body']),
        ));
        $attempt = $claim['attempt'];
        $code = $outcome->errorCode ?? 'max_error';
        $retry = MaxSendStatusEnum::RETRY === $outcome->status && self::MAX_ATTEMPTS > $attempt;
        $next = $this->transaction->execute(function() use ($outcome, $messageId, $attempt, $code, $retry, $claim): ?int {
            match (true) {
                $retry => $this->deliveries->retry($messageId, $attempt, $this->clock->now()->modify('+' . $this->delay($attempt) . ' seconds'), $code),
                MaxSendStatusEnum::DELIVERED === $outcome->status => $this->deliveries->delivered($messageId, $attempt, (string)$outcome->mid),
                MaxSendStatusEnum::UNKNOWN === $outcome->status => $this->deliveries->unknown($messageId, $attempt, $code),
                default => $this->deliveries->failed($messageId, $attempt, $code),
            };

            // A reply waiting for its retry keeps the later ones of this conversation behind it.
            return $retry ? null : $this->deliveries->nextPending($claim['questionId']);
        });
        if (null !== $next) {
            $this->publisher->publish($next);
        }
    }

    private function delay(int $attempt): int
    {
        return min(self::INITIAL_RETRY_SECONDS * (2 ** ($attempt - 1)), self::MAX_RETRY_SECONDS);
    }
}
