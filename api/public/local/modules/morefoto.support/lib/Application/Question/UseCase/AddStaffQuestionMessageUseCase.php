<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\StaffQuestionContextInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/**
 * Сотрудник учреждения пишет кураторам из кабинета: первая реплика создаёт его беседу, следующие дополняют её.
 * Имя и учреждения берутся из Access на момент сообщения, чтобы кураторы в MAX видели актуальный контекст.
 */
final readonly class AddStaffQuestionMessageUseCase
{
    public function __construct(
        private StaffQuestionContextInterface $staff,
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private QuestionMessageRecorder $recorder,
        private QuestionTextPolicy $policy,
        private MaxQuestionTextBuilder $texts,
        private QuestionHistory $history,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    public function execute(int $userId, string $message, IdempotencyKey $key): QuestionOutputDto
    {
        $text = $this->policy->message($message);
        $context = $this->staff->resolve($userId);
        $contextText = $this->texts->staffContext($context);
        $now = $this->clock->now();
        [$questionId, $messageId] = $this->transaction->execute(function() use ($userId, $context, $contextText, $text, $key, $now): array {
            $question = $this->questions->lockStaff($userId);
            if (null === $question) {
                $questionId = $this->questions->createStaff($userId, $context->name, $contextText, $now);
            } else {
                $questionId = $question['id'];
                $this->questions->refreshStaff($questionId, $context->name, $contextText);
            }

            return [$questionId, $this->recorder->append($questionId, AuthorEnum::STAFF, $context->name, $text, 'staff:' . $userId, $key, $now)];
        });
        if (null !== $messageId) {
            $this->publisher->publish($messageId);
        }

        return $this->history->output($questionId);
    }
}
