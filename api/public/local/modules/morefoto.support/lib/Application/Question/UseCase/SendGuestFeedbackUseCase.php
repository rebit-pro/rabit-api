<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\GuestAddressHasherInterface;
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
 * кураторов MAX, что и вопросы K3. Повтор с тем же Idempotency-Key возвращает тот же номер и не расходует лимит, даже
 * если параллельный запрос того же адреса только что занял последнее место; поток ограничен в час на хеш IP гостя,
 * чтобы один источник не занял общий лимит сайта, который остаётся предохранителем.
 */
final readonly class SendGuestFeedbackUseCase
{
    public const int GUEST_QUESTIONS_PER_HOUR = 30;
    public const int GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR = 5;
    private const string SCOPE = 'guest:login';

    public function __construct(
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private QuestionMessageRecorder $recorder,
        private QuestionTextPolicy $policy,
        private MaxQuestionTextBuilder $texts,
        private QuestionDeliveryPublisherInterface $publisher,
        private GuestAddressHasherInterface $addresses,
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

        [$questionId, $messageId] = $this->transaction->execute(function() use ($input, $keyHash, $payloadHash, $name, $contact, $message, $now): array {
            $hourAgo = $now->modify('-1 hour');
            $addressHash = $this->addresses->hash($input->clientAddress);
            $this->questions->forgetGuestAddresses($hourAgo);
            $addressQuestions = $this->questions->lockGuestAddress($addressHash, $now);
            // Read under the address lock: a parallel request with this key from the same address has already committed.
            $stored = $this->questions->idempotency(self::SCOPE, $keyHash);
            if (null !== $stored) {
                if (!hash_equals($stored['payloadHash'], $payloadHash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return [$stored['questionId'], null];
            }
            if (self::GUEST_QUESTIONS_PER_ADDRESS_PER_HOUR <= $addressQuestions
                || self::GUEST_QUESTIONS_PER_HOUR <= $this->questions->countGuestQuestions($hourAgo)) {
                throw new HttpException('RATE_LIMITED', 429);
            }
            $questionId = $this->questions->createGuest($name, $this->texts->guestContext($contact), $now);
            $messageId = $this->questions->addMessage($questionId, AuthorEnum::GUEST, $name, $message, $now);
            $this->questions->addGuestAddressQuestion($addressHash);
            $this->questions->remember(self::SCOPE, $keyHash, $payloadHash, $questionId, null, $now);

            return [$questionId, $messageId];
        });
        if (null !== $messageId) {
            $this->publisher->publish($messageId);
        }

        return $questionId;
    }
}
