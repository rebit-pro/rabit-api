<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Download\Repository;

use Morefoto\Files\Domain\Download\Exception\DuplicateDownloadException;
use Morefoto\Files\Domain\Download\ValueObject\Download;

interface DownloadRepositoryInterface
{
    /**
     * Pending ZIP занимает замок заказа: вторая одновременная сборка отклоняется уникальным ключом.
     *
     * @throws DuplicateDownloadException
     */
    public function insert(Download $download, string $idempotencyHash, \DateTimeImmutable $now): void;

    public function find(string $publicId): ?Download;

    public function byIdempotency(int $orderId, string $idempotencyHash): ?Download;

    public function pendingArchive(int $orderId): ?Download;

    public function reusableArchive(int $orderId, string $compositionHash, \DateTimeImmutable $now): ?Download;

    /** Забирает pending-сборку на срок аренды; false — её уже строит другой обработчик или попытки исчерпаны. */
    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool;

    public function markReady(string $publicId, string $archivePath, int $bytes, \DateTimeImmutable $expiresAt): void;

    public function markRetry(string $publicId, \DateTimeImmutable $nextAttemptAt): void;

    public function markFailed(string $publicId, string $errorCode, \DateTimeImmutable $expiresAt): void;

    public function markExpired(string $publicId): void;

    /** @return list<Download> pending-сборки, чья аренда или пауза повтора истекла */
    public function due(\DateTimeImmutable $now, int $limit): array;

    /** @return list<Download> готовые и неудачные загрузки с наступившим сроком */
    public function stale(\DateTimeImmutable $now, int $limit): array;
}
