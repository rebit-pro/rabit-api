<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Repository;

/** Состояние доставки реплик в MAX; каждое завершение защищено номером попытки. */
interface QuestionDeliveryRepositoryInterface
{
    /**
     * Переводит pending-реплику в processing и увеличивает счётчик попыток; зависшую processing — в unknown.
     *
     * @return null|array{
     *     id: int,
     *     attempt: int,
     *     questionId: int,
     *     questionAuthor: string,
     *     authorName: string,
     *     context: string,
     *     body: string,
     * }
     */
    public function claim(int $messageId, \DateTimeImmutable $now, \DateTimeImmutable $staleBefore): ?array;

    public function delivered(int $messageId, int $attempt, string $mid): void;

    public function retry(int $messageId, int $attempt, \DateTimeImmutable $nextAttemptAt, string $errorCode): void;

    public function failed(int $messageId, int $attempt, string $errorCode): void;

    public function unknown(int $messageId, int $attempt, string $errorCode): void;

    /** Processing дольше аренды — исход неизвестен; возвращает число таких реплик. */
    public function recoverStale(\DateTimeImmutable $staleBefore): int;

    /** @return list<int> pending-реплики, чей срок повтора наступил */
    public function due(\DateTimeImmutable $now, int $limit): array;
}
