<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Application\Question\Service\ParentQuestionAccess;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

/** Родитель дописывает свою беседу по личному ключу; новая реплика уходит кураторам в MAX с тем же номером вопроса. */
final readonly class AddGalleryQuestionMessageUseCase
{
    public function __construct(
        private ParentQuestionAccess $access,
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private QuestionMessageRecorder $recorder,
        private QuestionTextPolicy $policy,
        private QuestionHistory $history,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $questionKey, string $message, IdempotencyKey $key): QuestionOutputDto
    {
        $text = $this->policy->message($message);
        $question = $this->access->find($questionKey);
        $now = $this->clock->now();
        $messageId = $this->transaction->execute(function() use ($question, $text, $key, $now): ?int {
            $this->questions->lock($question['id']);

            return $this->recorder->append($question['id'], AuthorEnum::PARENT, $question['authorName'], $text, 'question:' . $question['id'], $key, $now);
        });
        if (null !== $messageId) {
            $this->publisher->publish($messageId);
        }

        return $this->history->output($question['id']);
    }
}
