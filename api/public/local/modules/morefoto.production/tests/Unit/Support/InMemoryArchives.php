<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit\Support;

use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveLine;
use Morefoto\Production\Domain\Archive\ValueObject\ArchiveVersion;
use Morefoto\Production\Domain\Archive\ValueObject\NewVersion;
use Morefoto\Production\Domain\Archive\ValueObject\ProductSummary;
use Morefoto\Production\Domain\Archive\ValueObject\ScheduleCursor;

/** Хранилище версий в памяти с теми же уникальными ключами, что у таблиц L1. */
final class InMemoryArchives implements ArchiveRepositoryInterface
{
    /** @var array<string, ArchiveVersion> */
    public array $versions = [];
    /** @var array<int, list<ArchiveLine>> */
    public array $lines = [];
    /** @var array<string, string> */
    public array $linkHashes = [];
    /** @var array<string, \DateTimeImmutable> */
    public array $leases = [];
    /** @var array<string, array{request: string, versionId: string}> */
    public array $requests = [];
    public ScheduleCursor $schedule;
    private int $nextId = 1;

    public function __construct(\DateTimeImmutable $since)
    {
        $this->schedule = new ScheduleCursor($since, $since, 0);
    }

    public function cursor(): ScheduleCursor
    {
        return $this->schedule;
    }

    public function advance(\DateTimeImmutable $cursorAt, int $cursorGroupId): void
    {
        $this->schedule = new ScheduleCursor($this->schedule->sinceAt, $cursorAt, $cursorGroupId);
    }

    public function create(NewVersion $version): bool
    {
        foreach ($this->versions as $existing) {
            if ($existing->groupId === $version->groupId && $existing->deadlineAt == $version->deadlineAt && $existing->number === $version->number) {
                return false;
            }
        }
        $taken = $this->includedLines($version->groupId, $version->deadlineAt);
        foreach ($version->lines as $line) {
            if (in_array($line->orderLineId, $taken, true)) {
                return false;
            }
        }
        $id = $this->nextId++;
        $this->lines[$id] = $version->lines;
        $this->versions[$version->publicId] = new ArchiveVersion(
            id: $id,
            publicId: $version->publicId,
            groupId: $version->groupId,
            groupPublicId: $version->groupPublicId,
            institutionId: $version->institutionId,
            deadlineAt: $version->deadlineAt,
            number: $version->number,
            status: $version->status,
            linesCount: count($version->lines),
            filesCount: $version->filesCount,
            printsCount: $version->printsCount,
            waitingOrders: $version->waitingOrders,
            archivePath: $version->archivePath,
            bytes: null,
            errorCode: null,
            attempts: 0,
            linkGeneration: 1,
            createdAt: $version->createdAt,
            readyAt: null,
            expiresAt: null,
            createdBy: $version->createdBy,
        );

        return true;
    }

    public function find(string $publicId): ?ArchiveVersion
    {
        return $this->versions[$publicId] ?? null;
    }

    public function findByLinkHash(string $linkHash): ?ArchiveVersion
    {
        $publicId = array_search($linkHash, $this->linkHashes, true);

        return false === $publicId ? null : $this->find($publicId);
    }

    public function byGroups(array $groupIds): array
    {
        $groups = [];
        foreach ($this->versions as $version) {
            if (in_array($version->groupId, $groupIds, true)) {
                $groups[$version->groupId][] = $version;
            }
        }
        foreach ($groups as &$versions) {
            usort($versions, static fn(ArchiveVersion $a, ArchiveVersion $b): int => $a->number <=> $b->number);
        }

        return $groups;
    }

    public function includedLines(int $groupId, \DateTimeImmutable $deadline): array
    {
        $ids = [];
        foreach ($this->versions as $version) {
            if ($version->groupId === $groupId && $version->deadlineAt == $deadline) {
                foreach ($this->lines[$version->id] as $line) {
                    $ids[] = $line->orderLineId;
                }
            }
        }

        return $ids;
    }

    public function nextNumber(int $groupId, \DateTimeImmutable $deadline): int
    {
        $max = 0;
        foreach ($this->versions as $version) {
            if ($version->groupId === $groupId && $version->deadlineAt == $deadline) {
                $max = max($max, $version->number);
            }
        }

        return $max + 1;
    }

    public function lines(int $versionId): array
    {
        return $this->lines[$versionId] ?? [];
    }

    public function products(array $versionIds): array
    {
        $products = [];
        foreach ($versionIds as $versionId) {
            $byProduct = [];
            foreach ($this->lines[$versionId] ?? [] as $line) {
                $byProduct[$line->productId]['name'] = $line->productName;
                $byProduct[$line->productId]['format'] = $line->productFormat;
                $byProduct[$line->productId]['entries'][$line->entry] = true;
                $byProduct[$line->productId]['prints'] = ($byProduct[$line->productId]['prints'] ?? 0) + $line->prints;
            }
            foreach ($byProduct as $productId => $item) {
                $products[$versionId][] = new ProductSummary((string)$productId, $item['name'], $item['format'], count($item['entries']), $item['prints']);
            }
        }

        return $products;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $due = [];
        foreach ($this->versions as $version) {
            if (VersionStatusEnum::PENDING === $version->status && (!isset($this->leases[$version->publicId]) || $this->leases[$version->publicId] <= $now)) {
                $due[] = $version;
            }
        }

        return array_slice($due, 0, $limit);
    }

    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool
    {
        $version = $this->versions[$publicId] ?? null;
        if (null === $version || VersionStatusEnum::PENDING !== $version->status || $version->attempts >= $maxAttempts
            || (isset($this->leases[$publicId]) && $this->leases[$publicId] > $now)) {
            return false;
        }
        $this->leases[$publicId] = $leaseUntil;
        $this->replace($publicId, ['attempts' => $version->attempts + 1]);

        return true;
    }

    public function markReady(string $publicId, int $files, int $bytes, string $linkHash, \DateTimeImmutable $readyAt, \DateTimeImmutable $expiresAt): void
    {
        unset($this->leases[$publicId]);
        $this->linkHashes[$publicId] = $linkHash;
        $this->replace($publicId, ['status' => VersionStatusEnum::READY, 'filesCount' => $files, 'bytes' => $bytes, 'readyAt' => $readyAt, 'expiresAt' => $expiresAt, 'errorCode' => null]);
    }

    public function markRetry(string $publicId, \DateTimeImmutable $retryAt): void
    {
        $this->leases[$publicId] = $retryAt;
    }

    public function markFailed(string $publicId, string $errorCode): void
    {
        unset($this->leases[$publicId]);
        $this->replace($publicId, ['status' => VersionStatusEnum::FAILED, 'errorCode' => $errorCode]);
    }

    public function expired(\DateTimeImmutable $now, int $limit): array
    {
        return array_values(array_filter($this->versions, static fn(ArchiveVersion $v): bool => VersionStatusEnum::READY === $v->status && null !== $v->expiresAt && $v->expiresAt <= $now));
    }

    public function markExpired(string $publicId): void
    {
        unset($this->linkHashes[$publicId]);
        $this->replace($publicId, ['status' => VersionStatusEnum::EXPIRED]);
    }

    public function renew(string $publicId, int $expectedGeneration, ?string $linkHash): bool
    {
        $version = $this->versions[$publicId] ?? null;
        if (null === $version || $version->linkGeneration !== $expectedGeneration) {
            return false;
        }
        if (null === $linkHash) {
            if (!in_array($version->status, [VersionStatusEnum::EXPIRED, VersionStatusEnum::FAILED], true)) {
                return false;
            }
            unset($this->linkHashes[$publicId], $this->leases[$publicId]);
            $this->replace($publicId, ['status' => VersionStatusEnum::PENDING, 'attempts' => 0, 'errorCode' => null, 'bytes' => null, 'readyAt' => null, 'expiresAt' => null, 'linkGeneration' => $expectedGeneration + 1]);

            return true;
        }
        if (VersionStatusEnum::READY !== $version->status) {
            return false;
        }
        $this->linkHashes[$publicId] = $linkHash;
        $this->replace($publicId, ['linkGeneration' => $expectedGeneration + 1]);

        return true;
    }

    public function idempotency(int $actorId, string $keyHash): ?array
    {
        return $this->requests[$actorId . '|' . $keyHash] ?? null;
    }

    public function remember(int $actorId, string $keyHash, string $requestHash, string $versionId): void
    {
        $this->requests[$actorId . '|' . $keyHash] ??= ['request' => $requestHash, 'versionId' => $versionId];
    }

    /** @param array<string, mixed> $changes */
    private function replace(string $publicId, array $changes): void
    {
        $values = get_object_vars($this->versions[$publicId]);
        $this->versions[$publicId] = new ArchiveVersion(...array_merge($values, $changes));
    }
}
