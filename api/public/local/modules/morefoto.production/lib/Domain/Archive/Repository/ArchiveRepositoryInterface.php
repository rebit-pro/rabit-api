<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Archive\Repository;

use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Archive\ValueObject\NewVersion;
use Morefoto\Production\Domain\Archive\ValueObject\ProductSummary;
use Morefoto\Production\Domain\Archive\ValueObject\ScheduleCursor;

/** Версии архива печати, их неизменяемые строки и курсор планировщика. */
interface ArchiveRepositoryInterface
{
    public function cursor(): ScheduleCursor;

    public function advance(\DateTimeImmutable $cursorAt, int $cursorGroupId): void;

    /**
     * Записывает версию и строки в одной транзакции. Уникальность группа + срок + номер и группа + срок + строка заказа
     * не даёт двум сборкам создать одну версию дважды или включить строку в две версии.
     *
     * @return bool false — такую версию или строку уже записал параллельный процесс, ничего не изменено
     */
    public function create(NewVersion $version): bool;

    public function find(string $publicId): ?ArchiveVersion;

    public function findByLinkHash(string $linkHash): ?ArchiveVersion;

    /**
     * Версии групп по возрастанию номера.
     *
     * @param list<int> $groupIds
     *
     * @return array<int, list<ArchiveVersion>> ключ — внутренний ID группы
     */
    public function byGroups(array $groupIds): array;

    /** @return list<string> публичные ID строк заказов, уже вошедших в версии этого срока */
    public function includedLines(int $groupId, \DateTimeImmutable $deadline): array;

    public function nextNumber(int $groupId, \DateTimeImmutable $deadline): int;

    /** @return list<ArchiveLine> */
    public function lines(int $versionId): array;

    /**
     * @param list<int> $versionIds
     *
     * @return array<int, list<ProductSummary>> ключ — внутренний ID версии
     */
    public function products(array $versionIds): array;

    /** @return list<ArchiveVersion> ожидающие сборки версии без действующей аренды */
    public function due(\DateTimeImmutable $now, int $limit): array;

    /** Берёт аренду сборки и учитывает попытку; false — версию уже собирает другой процесс или она не ждёт сборки. */
    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool;

    public function markReady(string $publicId, int $files, int $bytes, string $linkHash, \DateTimeImmutable $readyAt, \DateTimeImmutable $expiresAt): void;

    public function markRetry(string $publicId, \DateTimeImmutable $retryAt): void;

    public function markFailed(string $publicId, string $errorCode): void;

    /** @return list<ArchiveVersion> готовые версии с истёкшим сроком хранения */
    public function expired(\DateTimeImmutable $now, int $limit): array;

    public function markExpired(string $publicId): void;

    /**
     * Перевыпускает ссылку: новое поколение отзывает прежнюю. Удалённый по сроку или неудавшийся архив снова ждёт сборки.
     *
     * @param null|string $linkHash хеш ссылки нового поколения для готовой версии; null — версия снова ждёт сборки
     *
     * @return bool false — версию изменили параллельно, ничего не изменено
     */
    public function renew(string $publicId, int $expectedGeneration, ?string $linkHash): bool;

    /** @return null|array{request: string, versionId: string} ответ на ранее принятый Idempotency-Key сотрудника */
    public function idempotency(int $actorId, string $keyHash): ?array;

    public function remember(int $actorId, string $keyHash, string $requestHash, string $versionId): void;
}
