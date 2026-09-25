<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/**
 * Периодически восстанавливает доставку: зависшие попытки переводит в неизвестный исход, а реплики с наступившим сроком
 * повтора или потерянным сигналом RabbitMQ снова публикует worker.
 */
final readonly class DispatchPendingQuestionMessagesUseCase
{
    public function __construct(
        private SupportTransactionInterface $transaction,
        private QuestionDeliveryRepositoryInterface $deliveries,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    public function execute(int $limit): int
    {
        $now = $this->clock->now();
        $this->transaction->execute(fn(): int => $this->deliveries->recoverStale($now->modify('-' . DeliverQuestionMessageUseCase::LEASE_SECONDS . ' seconds')));
        $due = $this->deliveries->due($now, $limit);
        foreach ($due as $messageId) {
            $this->publisher->publish($messageId);
        }

        return count($due);
    }
}
