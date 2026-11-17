<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Institution\Repository;

use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;

/** @phpstan-type ArchiveSummary array{
 *     id: string, status: string, files: int, attempts: int, bytes: null|int, error: null|string,
 *     capturedAt: \DateTimeImmutable, readyAt: null|\DateTimeImmutable, expiresAt: null|\DateTimeImmutable,
 * }
 */
interface DigitalArchiveRepositoryInterface
{
    /** @template T
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function exclusive(string $scope, \Closure $operation): mixed;

    public function find(string $id): ?DigitalArchive;

    public function request(int $actorId, string $keyHash): ?DigitalArchive;

    /** @return list<ArchiveSummary> последние 20 выпусков без чтения состава, от нового к старому */
    public function latest(int $institutionId): array;

    public function pending(int $institutionId): ?DigitalArchive;

    public function insert(DigitalArchive $archive): void;

    /** CAS: захватывает только созревшую pending-попытку, сохраняет уникальный путь до файлового эффекта. */
    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool;

    public function ready(string $id, string $owner, string $path, int $bytes, \DateTimeImmutable $at, \DateTimeImmutable $expires): bool;

    public function failed(string $id, ?string $owner, string $error, \DateTimeImmutable $at): bool;

    public function retry(string $id, string $owner, \DateTimeImmutable $at): void;

    /** @return list<array{id: string, attempts: int}> */
    public function due(\DateTimeImmutable $now, int $limit): array;

    /** @return list<string> ID ready/failed с истёкшим сроком и остатками попыток */
    public function stale(\DateTimeImmutable $now, int $limit): array;

    /** Состав сохраняется, пути очищаются только после успешного удаления файлов. */
    public function purged(string $id, \DateTimeImmutable $now): void;
}
