<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Repository;

/** Состояние доставки реплик в MAX; каждое завершение защищено номером попытки. */
interface QuestionDeliveryRepositoryInterface
{
    /**
     * Переводит pending-реплику в processing и увеличивает счётчик попыток; зависшую processing — в unknown.
     * Реплика выдаётся, только если раньше неё в беседе нет незавершённых (pending/processing): куратор видит порядок автора.
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

    /** @return list<int> первые в своей беседе pending-реплики, чей срок повтора наступил */
    public function due(\DateTimeImmutable $now, int $limit): array;

    /** Самая ранняя pending-реплика беседы — следующая после завершённой. */
    public function nextPending(int $questionId): ?int;

    /** @return array<string, int> число реплик авторов по состоянию доставки */
    public function countByStatus(): array;
}
