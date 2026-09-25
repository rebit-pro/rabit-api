<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\UseCase;

use Morefoto\Support\Application\Question\Contract\GalleryQuestionContextInterface;
use Morefoto\Support\Application\Question\Contract\QuestionDeliveryPublisherInterface;
use Morefoto\Support\Application\Question\Contract\QuestionKeySealInterface;
use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;
use Morefoto\Support\Application\Question\Dto\AskGalleryQuestionInputDto;
use Morefoto\Support\Application\Question\Dto\QuestionOutputDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Application\Question\Service\QuestionHistory;
use Morefoto\Support\Application\Question\Service\QuestionMessageRecorder;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Родитель без аккаунта задаёт первый вопрос из галереи: создаются беседа группы, реплика-задание доставки в MAX
 * и личный ключ беседы. Повтор с тем же Idempotency-Key возвращает ту же беседу и тот же ключ.
 */
final readonly class AskGalleryQuestionUseCase
{
    public const int QUESTIONS_PER_GROUP_HOUR = 30;

    public function __construct(
        private GalleryQuestionContextInterface $galleries,
        private SupportTransactionInterface $transaction,
        private QuestionRepositoryInterface $questions,
        private QuestionMessageRecorder $recorder,
        private QuestionKeySealInterface $seal,
        private QuestionTextPolicy $policy,
        private MaxQuestionTextBuilder $texts,
        private QuestionHistory $history,
        private QuestionDeliveryPublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    public function execute(AskGalleryQuestionInputDto $input, IdempotencyKey $key): QuestionOutputDto
    {
        $name = $this->policy->name($input->name);
        $message = $this->policy->message($input->message);
        $context = $this->galleries->resolve($input->galleryToken);
        $scope = 'gallery:' . hash('sha256', $input->galleryToken);
        $keyHash = $this->recorder->keyHash($key);
        $payloadHash = hash('sha256', json_encode([$name, $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $now = $this->clock->now();

        [$questionId, $questionKey, $messageId] = $this->transaction->execute(function() use ($context, $scope, $keyHash, $payloadHash, $key, $name, $message, $now): array {
            $stored = $this->questions->idempotency($scope, $keyHash);
            if (null !== $stored) {
                if (!hash_equals($stored['payloadHash'], $payloadHash) || null === $stored['sealedKey']) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }

                return [$stored['questionId'], $this->seal->open($stored['sealedKey'], $key->value, $scope), null];
            }
            if (self::QUESTIONS_PER_GROUP_HOUR <= $this->questions->countParentQuestions($context->groupId, $now->modify('-1 hour'))) {
                throw new HttpException('RATE_LIMITED', 429);
            }
            $questionKey = bin2hex(random_bytes(32));
            $questionId = $this->questions->createParent(hash('sha256', $questionKey), $context->groupId, $name, $this->texts->galleryContext($context), $now);
            $messageId = $this->questions->addMessage($questionId, AuthorEnum::PARENT, $name, $message, $now);
            $this->questions->remember($scope, $keyHash, $payloadHash, $questionId, $this->seal->seal($questionKey, $key->value, $scope), $now);

            return [$questionId, $questionKey, $messageId];
        });
        if (null !== $messageId) {
            $this->publisher->publish($messageId);
        }

        return $this->history->output($questionId, $questionKey);
    }
}
