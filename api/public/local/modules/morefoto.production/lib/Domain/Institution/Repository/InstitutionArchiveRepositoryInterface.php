<?php

declare(strict_types=1);

namespace Morefoto\Production\Domain\Institution\Repository;

use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Morefoto\Production\Domain\Institution\ValueObject\NewInstitutionArchive;

interface InstitutionArchiveRepositoryInterface
{
    public function cursor(): int;

    public function advance(int $id): void;

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function exclusive(string $key, \Closure $operation): mixed;

    public function create(NewInstitutionArchive $archive, ?InstitutionArchiveRequest $request = null): InstitutionArchive;

    public function find(string $id): ?InstitutionArchive;

    /** @return list<InstitutionArchive> вся история в порядке номера, без скрытого отсечения источников */
    public function history(int $institutionId): array;

    public function planningError(int $institutionId): ?string;

    public function recordPlanningError(int $institutionId, ?string $error): void;

    /** @return list<InstitutionArchive> */
    public function due(\DateTimeImmutable $now, int $limit): array;

    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool;

    public function ready(string $id, string $owner, int $bytes, \DateTimeImmutable $now, \DateTimeImmutable $expires): bool;

    public function failed(string $id, string $owner, string $error, ?\DateTimeImmutable $retryAt): bool;

    /** @return list<InstitutionArchive> */
    public function expired(\DateTimeImmutable $now, int $limit): array;

    public function expire(string $id, int $generation): void;

    public function renew(string $id, int $generation, bool $rebuild, InstitutionArchiveRequest $request): bool;

    /** @return null|array{
     *     request: string,
     *     archiveId: string,
     * } */
    public function request(int $actorId, string $keyHash): ?array;
}
