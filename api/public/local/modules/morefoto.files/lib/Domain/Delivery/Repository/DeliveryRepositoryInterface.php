<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\Repository;

use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;

/** Журнал писем со ссылкой на заказ и курсор подтверждённых оплат. */
interface DeliveryRepositoryInterface
{
    /** @return array{at: \DateTimeImmutable, orderId: int} последнее подтверждение оплаты, уже разобранное диспетчером */
    public function cursor(): array;

    public function advance(\DateTimeImmutable $at, int $orderId): void;

    /** Одно автоматическое письмо на заказ: повтор диспетчера ничего не меняет. @return bool письмо записано впервые */
    public function createAuto(string $publicId, int $orderId, string $recipient, \DateTimeImmutable $now): bool;

    /** @return bool false — письмо с этим Idempotency-Key уже записано */
    public function createManual(string $publicId, int $orderId, string $recipient, string $idempotencyHash, \DateTimeImmutable $now): bool;

    /** @phpstan-impure */
    public function find(string $publicId): ?FileDelivery;

    /** @phpstan-impure */
    public function findByIdempotency(int $orderId, string $idempotencyHash): ?FileDelivery;

    /** @phpstan-impure */
    public function latest(int $orderId): ?FileDelivery;

    public function countSince(int $orderId, \DateTimeImmutable $since): int;

    /** @return list<FileDelivery> письма, ещё не поставленные в очередь H1 */
    public function held(int $limit): array;

    public function markQueued(string $publicId, string $operationId, \DateTimeImmutable $now): void;

    public function markCancelled(string $publicId, \DateTimeImmutable $now): void;
}
