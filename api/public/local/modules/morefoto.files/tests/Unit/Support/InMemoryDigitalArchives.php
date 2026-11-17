<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit\Support;

use Morefoto\Files\Domain\Institution\Exception\DigitalArchiveException;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\Service\DigitalArchivePolicy;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;

/** Однопоточный двойник для сценариев; реальные GET_LOCK/CAS/Result отдельно проверяются на MySQL. */
final class InMemoryDigitalArchives implements DigitalArchiveRepositoryInterface
{
    /** @var array<string, DigitalArchive> */
    public array $records = [];
    /** @var array<string, \DateTimeImmutable> */
    public array $next = [];
    /** @var array<string, true> */
    private array $locks = [];

    public function exclusive(string $scope, \Closure $operation): mixed
    {
        if (isset($this->locks[$scope])) {
            throw new DigitalArchiveException('OPERATION_IN_PROGRESS');
        }
        $this->locks[$scope] = true;
        try {
            return $operation();
        } finally {
            unset($this->locks[$scope]);
        }
    }

    public function find(string $id): ?DigitalArchive
    {
        return $this->records[$id] ?? null;
    }

    public function request(int $actorId, string $keyHash): ?DigitalArchive
    {
        foreach ($this->records as $archive) {
            if ($actorId === $archive->actorId && $keyHash === $archive->keyHash) {
                return $archive;
            }
        }

        return null;
    }

    public function latest(int $institutionId): array
    {
        $records = array_slice(array_values(array_filter(array_reverse($this->records), static fn(DigitalArchive $archive): bool => $institutionId === $archive->institutionId)), 0, 20);

        return array_map(static fn(DigitalArchive $archive): array => [
            'id' => $archive->id, 'status' => $archive->status, 'files' => count($archive->files), 'attempts' => $archive->attempts,
            'bytes' => $archive->bytes, 'error' => $archive->error, 'capturedAt' => $archive->capturedAt,
            'readyAt' => $archive->readyAt, 'expiresAt' => $archive->expiresAt,
        ], $records);
    }

    public function pending(int $institutionId): ?DigitalArchive
    {
        foreach ($this->records as $archive) {
            if ($institutionId === $archive->institutionId && 'pending' === $archive->status) {
                return $archive;
            }
        }

        return null;
    }

    public function insert(DigitalArchive $archive): void
    {
        $this->records[$archive->id] = $archive;
        $this->next[$archive->id] = $archive->capturedAt;
    }

    public function claim(string $id, string $owner, string $path, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        $archive = $this->find($id);
        if (null === $archive || 'pending' !== $archive->status || DigitalArchivePolicy::MAX_ATTEMPTS <= $archive->attempts || $this->next[$id] > $now) {
            return false;
        }
        $paths = $archive->workPaths;
        $paths[] = $path;
        $this->change($id, ['owner' => $owner, 'attempts' => $archive->attempts + 1, 'workPaths' => $paths]);
        $this->next[$id] = $until;

        return true;
    }

    public function ready(string $id, string $owner, string $path, int $bytes, \DateTimeImmutable $at, \DateTimeImmutable $expires): bool
    {
        $archive = $this->find($id);
        if (null === $archive || 'pending' !== $archive->status || $owner !== $archive->owner) {
            return false;
        }
        $this->change($id, ['status' => 'ready', 'archivePath' => $path, 'bytes' => $bytes, 'readyAt' => $at, 'expiresAt' => $expires]);

        return true;
    }

    public function failed(string $id, ?string $owner, string $error, \DateTimeImmutable $at): bool
    {
        $archive = $this->find($id);
        if (null === $archive || 'pending' !== $archive->status || $owner !== $archive->owner) {
            return false;
        }
        $this->change($id, ['status' => 'failed', 'error' => $error, 'expiresAt' => $at]);

        return true;
    }

    public function retry(string $id, string $owner, \DateTimeImmutable $at): void
    {
        if ($owner !== $this->records[$id]->owner) {
            throw new \RuntimeException('Wrong owner.');
        }
        $this->change($id, ['owner' => null]);
        $this->next[$id] = $at;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $records = array_slice(array_values(array_filter($this->records, fn(DigitalArchive $archive): bool => 'pending' === $archive->status && $this->next[$archive->id] <= $now)), 0, $limit);

        return array_map(static fn(DigitalArchive $archive): array => ['id' => $archive->id, 'attempts' => $archive->attempts], $records);
    }

    public function stale(\DateTimeImmutable $now, int $limit): array
    {
        $records = array_slice(array_values(array_filter($this->records, static fn(DigitalArchive $archive): bool => in_array($archive->status, ['ready', 'failed'], true)
            && null !== $archive->expiresAt && $archive->expiresAt <= $now && [] !== $archive->workPaths)), 0, $limit);

        return array_map(static fn(DigitalArchive $archive): string => $archive->id, $records);
    }

    public function purged(string $id, \DateTimeImmutable $now): void
    {
        $archive = $this->records[$id];
        $this->change($id, ['status' => 'ready' === $archive->status ? 'expired' : $archive->status, 'archivePath' => null, 'workPaths' => [], 'owner' => null]);
    }

    /** @param array<string, mixed> $changes */
    private function change(string $id, array $changes): void
    {
        $values = get_object_vars($this->records[$id]);
        foreach ($changes as $key => $value) {
            $values[$key] = $value;
        }
        $this->records[$id] = new DigitalArchive(...$values);
    }
}
