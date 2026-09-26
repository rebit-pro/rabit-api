<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Dto\SendGuestFeedbackInputDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Гость без аккаунта пишет со страницы входа: обращение с его контактом ставится в ту же очередь доставки в группу
 * кураторов MAX, что и вопросы K3. Повтор с тем же Idempotency-Key возвращает тот же номер, общий поток ограничен в час.
 */
final readonly class SendGuestFeedbackUseCase
{
    public const int GUEST_QUESTIONS_PER_HOUR = 30;
    private const string SCOPE = 'guest:login';

    public function __construct(
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private QuestionMessageRecorder $recorder,
        private QuestionTextPolicy $policy,
        private MaxQuestionTextBuilder $texts,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    /** @return int номер обращения для ответа гостю */
    public function execute(SendGuestFeedbackInputDto $input, IdempotencyKey $key): int
    {
        $name = $this->policy->name($input->name);
        $contact = $this->policy->contact($input->contact);
        $message = $this->policy->message($input->message);
        $keyHash = $this->recorder->keyHash($key);
        $payloadHash = hash('sha256', json_encode([$name, $contact, $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $now = $this->clock->now();

        [$questionId, $messageId] = $this->transaction->execute(function() use ($keyHash, $payloadHash, $name, $contact, $message, $now): array {
            $stored = $this->questions->idempotency(self::SCOPE, $keyHash);
            if (null !== $stored) {
                if (!hash_equals($stored['payloadHash'], $payloadHash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return [$stored['questionId'], null];
            }
            if (self::GUEST_QUESTIONS_PER_HOUR <= $this->questions->countGuestQuestions($now->modify('-1 hour'))) {
                throw new HttpException('RATE_LIMITED', 429);
            }
            $questionId = $this->questions->createGuest($name, $this->texts->guestContext($contact), $now);
            $messageId = $this->questions->addMessage($questionId, AuthorEnum::GUEST, $name, $message, $now);
            $this->questions->remember(self::SCOPE, $keyHash, $payloadHash, $questionId, null, $now);

            return [$questionId, $messageId];
        });
        if (null !== $messageId) {
            $this->publisher->publish($messageId);
        }

        return $questionId;
    }
}
