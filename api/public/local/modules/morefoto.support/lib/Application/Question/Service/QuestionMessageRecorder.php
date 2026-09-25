<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Service;

use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;
use Morefoto\Support\Domain\Question\ValueObject\IdempotencyKey;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Записывает следующую реплику автора внутри транзакции сценария: повтор с тем же ключом не создаёт вторую реплику,
 * другое тело с тем же ключом — конфликт, а частые сообщения одной беседы ограничены.
 */
final readonly class QuestionMessageRecorder
{
    public const int MESSAGES_PER_HOUR = 20;

    public function __construct(private QuestionRepositoryInterface $questions) {}

    /** @return null|int ID новой реплики; null — повтор уже выполненной операции */
    public function append(int $questionId, AuthorEnum $author, string $authorName, string $text, string $scope, IdempotencyKey $key, \DateTimeImmutable $now): ?int
    {
        $keyHash = $this->keyHash($key);
        $payloadHash = hash('sha256', $text);
        $stored = $this->questions->idempotency($scope, $keyHash);
        if (null !== $stored) {
            if (!hash_equals($stored['payloadHash'], $payloadHash) || $stored['questionId'] !== $questionId) {
                throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
            }

            return null;
        }
        if (self::MESSAGES_PER_HOUR <= $this->questions->countOwnMessages($questionId, $now->modify('-1 hour'))) {
            throw new HttpException('RATE_LIMITED', 429);
        }
        $messageId = $this->questions->addMessage($questionId, $author, $authorName, $text, $now);
        $this->questions->remember($scope, $keyHash, $payloadHash, $questionId, null, $now);

        return $messageId;
    }

    /** В БД хранится только хеш клиентского ключа: сам ключ расшифровывает ключ беседы при повторе. */
    public function keyHash(IdempotencyKey $key): string
    {
        return hash('sha256', 'morefoto.support.idempotency|' . $key->value);
    }
}
