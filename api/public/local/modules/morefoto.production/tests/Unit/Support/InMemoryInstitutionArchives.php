<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit\Support;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Institution\Exception\InstitutionArchiveBusyException;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchiveRequest;
use Morefoto\Production\Domain\Institution\ValueObject\NewInstitutionArchive;

final class InMemoryInstitutionArchives implements InstitutionArchiveRepositoryInterface
{
    /** @var array<string, InstitutionArchive> */
    public array $items = [];
    /** @var array<string, array{
     *     owner: string,
     *     until: \DateTimeImmutable,
     * }> */
    public array $leases = [];
    /** @var array<string, bool> */
    public array $locks = [];
    /** @var array<int, ?string> */
    private array $errors = [];
    /** @var array<string, array{
     *     request: string,
     *     archiveId: string,
     * }> */
    private array $requests = [];
    private int $cursorId = 0;
    public bool $failRemember = false;

    public function cursor(): int
    {
        return $this->cursorId;
    }

    public function advance(int $id): void
    {
        $this->cursorId = $id;
    }

    public function exclusive(string $key, \Closure $operation): mixed
    {
        if (isset($this->locks[$key])) {
            throw new InstitutionArchiveBusyException();
        }
        $this->locks[$key] = true;
        try {
            return $operation();
        } finally {
            unset($this->locks[$key]);
        }
    }

    public function create(NewInstitutionArchive $archive, ?InstitutionArchiveRequest $request = null): InstitutionArchive
    {
        return $this->atomic(function() use ($archive, $request): InstitutionArchive {
            $saved = $this->insert($archive);
            if (null !== $request) {
                $this->remember($request, $saved->publicId);
            }

            return $saved;
        });
    }

    private function insert(NewInstitutionArchive $archive): InstitutionArchive
    {
        $number = 1;
        foreach ($this->history($archive->institutionId) as $item) {
            if ($item->fingerprint === $archive->fingerprint && $item->kind === $archive->kind) {
                return $item;
            }
            $number = max($number, $item->number + 1);
        }

        return $this->items[$archive->publicId] = new InstitutionArchive(
            publicId: $archive->publicId,
            institutionId: $archive->institutionId,
            institutionPublicId: $archive->institutionPublicId,
            number: $number,
            kind: $archive->kind,
            fingerprint: $archive->fingerprint,
            groups: $archive->groups,
            lines: $archive->lines,
            files: $archive->files,
            prints: $archive->prints,
            status: 0 === $archive->lines ? VersionStatusEnum::EMPTY : VersionStatusEnum::PENDING,
            error: null,
            attempts: 0,
            generation: 1,
            archivePath: null,
            workPath: null,
            obsoletePath: null,
            bytes: null,
            createdAt: $archive->createdAt,
            readyAt: null,
            firstReadyAt: null,
            expiresAt: null,
        );
    }

    public function find(string $id): ?InstitutionArchive
    {
        return $this->items[$id] ?? null;
    }

    public function history(int $institutionId): array
    {
        $items = array_values(array_filter($this->items, static fn(InstitutionArchive $item): bool => $item->institutionId === $institutionId));
        usort($items, static fn(InstitutionArchive $a, InstitutionArchive $b): int => $a->number <=> $b->number);

        return $items;
    }

    public function planningError(int $institutionId): ?string
    {
        return $this->errors[$institutionId] ?? null;
    }

    public function recordPlanningError(int $institutionId, ?string $error): void
    {
        $this->errors[$institutionId] = $error;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        return array_slice(array_values(array_filter($this->items, fn(InstitutionArchive $item): bool => VersionStatusEnum::PENDING === $item->status
            && (!isset($this->leases[$item->publicId]) || $this->leases[$item->publicId]['until'] <= $now))), 0, $limit);
    }

    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        $item = $this->find($id);
        if (null === $item || VersionStatusEnum::PENDING !== $item->status
            || (isset($this->leases[$id]) && $this->leases[$id]['until'] > $now)) {
            return false;
        }
        $this->leases[$id] = ['owner' => $owner, 'until' => $until];
        $this->replace($id, ['attempts' => min($item->attempts + 1, ArchivePolicy::MAX_ATTEMPTS + 1), 'workPath' => $path]);

        return true;
    }

    public function ready(string $id, string $owner, int $bytes, \DateTimeImmutable $now, \DateTimeImmutable $expires): bool
    {
        $lease = $this->leases[$id] ?? null;
        if (null === $lease || $lease['owner'] !== $owner || $lease['until'] <= $now) {
            return false;
        }
        $this->replace($id, ['status' => VersionStatusEnum::READY, 'archivePath' => $this->items[$id]->workPath, 'workPath' => null,
            'obsoletePath' => null, 'bytes' => $bytes, 'readyAt' => $now, 'firstReadyAt' => $this->items[$id]->firstReadyAt ?? $now, 'expiresAt' => $expires, 'error' => null]);
        unset($this->leases[$id]);

        return true;
    }

    public function failed(string $id, string $owner, string $error, ?\DateTimeImmutable $retryAt): bool
    {
        if (($this->leases[$id]['owner'] ?? null) !== $owner) {
            return false;
        }
        $this->replace($id, ['status' => null === $retryAt ? VersionStatusEnum::FAILED : VersionStatusEnum::PENDING, 'error' => $error, 'workPath' => null]);
        if (null === $retryAt) {
            unset($this->leases[$id]);
        } else {
            $this->leases[$id] = ['owner' => '', 'until' => $retryAt];
        }

        return true;
    }

    public function expired(\DateTimeImmutable $now, int $limit): array
    {
        return array_slice(array_values(array_filter($this->items, static fn(InstitutionArchive $item): bool => VersionStatusEnum::READY === $item->status && null !== $item->expiresAt && $item->expiresAt <= $now)), 0, $limit);
    }

    public function expire(string $id, int $generation): void
    {
        if ($this->items[$id]->generation === $generation && VersionStatusEnum::READY === $this->items[$id]->status) {
            $this->replace($id, ['status' => VersionStatusEnum::EXPIRED]);
        }
    }

    public function renew(string $id, int $generation, bool $rebuild, InstitutionArchiveRequest $request): bool
    {
        return $this->atomic(function() use ($id, $generation, $rebuild, $request): bool {
            if (!$this->updateGeneration($id, $generation, $rebuild)) {
                return false;
            }
            $this->remember($request, $id);

            return true;
        });
    }

    private function updateGeneration(string $id, int $generation, bool $rebuild): bool
    {
        $item = $this->items[$id];
        if ($item->generation !== $generation || in_array($item->status, [VersionStatusEnum::PENDING, VersionStatusEnum::EMPTY], true)) {
            return false;
        }
        $changes = ['generation' => $generation + 1];
        if ($rebuild) {
            $changes += ['status' => VersionStatusEnum::PENDING, 'attempts' => 0, 'error' => null, 'obsoletePath' => $item->archivePath ?? $item->obsoletePath, 'archivePath' => null, 'workPath' => null, 'bytes' => null, 'readyAt' => null, 'expiresAt' => null];
            unset($this->leases[$id]);
        }
        $this->replace($id, $changes);

        return true;
    }

    public function request(int $actorId, string $keyHash): ?array
    {
        return $this->requests[$actorId . ':' . $keyHash] ?? null;
    }

    private function remember(InstitutionArchiveRequest $request, string $archiveId): void
    {
        if ($this->failRemember) {
            throw new ProductionStorageException('Injected request persistence failure.');
        }
        $key = $request->actorId . ':' . $request->keyHash;
        if (isset($this->requests[$key])) {
            throw new \LogicException('Duplicate request.');
        }
        $this->requests[$key] = ['request' => $request->requestHash, 'archiveId' => $archiveId];
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    private function atomic(\Closure $operation): mixed
    {
        $items = $this->items;
        $leases = $this->leases;
        $requests = $this->requests;
        try {
            return $operation();
        } catch (\Throwable $error) {
            $this->items = $items;
            $this->leases = $leases;
            $this->requests = $requests;
            throw $error;
        }
    }

    /** @param array<string, mixed> $changes */
    private function replace(string $id, array $changes): void
    {
        $this->items[$id] = new InstitutionArchive(...array_replace(get_object_vars($this->items[$id]), $changes));
    }
}
