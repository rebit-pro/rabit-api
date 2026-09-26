<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit\Support;

use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Domain\Download\Enum\DownloadStatusEnum;
use Morefoto\Files\Domain\Download\Exception\DuplicateDownloadException;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Morefoto\Files\Domain\Download\ValueObject\Download;

/** Повторяет уникальные ключи и переходы BitrixDownloadRepository в памяти. */
final class InMemoryDownloads implements DownloadRepositoryInterface
{
    /** @var array<string, Download> */
    public array $rows = [];

    /** @var array<string, string> ключ «заказ:хеш» → ID загрузки */
    private array $keys = [];

    public function insert(Download $download, string $idempotencyHash, \DateTimeImmutable $now): void
    {
        $key = $download->orderId . ':' . $idempotencyHash;
        $locked = DownloadKindEnum::ZIP === $download->kind && DownloadStatusEnum::PENDING === $download->status;
        if (isset($this->keys[$key]) || ($locked && null !== $this->pendingArchive($download->orderId))) {
            throw new DuplicateDownloadException('taken');
        }
        $this->keys[$key] = $download->publicId;
        $this->rows[$download->publicId] = $download;
    }

    public function find(string $publicId): ?Download
    {
        return $this->rows[$publicId] ?? null;
    }

    public function byIdempotency(int $orderId, string $idempotencyHash): ?Download
    {
        $id = $this->keys[$orderId . ':' . $idempotencyHash] ?? null;

        return null === $id ? null : $this->rows[$id];
    }

    public function pendingArchive(int $orderId): ?Download
    {
        foreach ($this->rows as $row) {
            if ($row->orderId === $orderId && DownloadKindEnum::ZIP === $row->kind && DownloadStatusEnum::PENDING === $row->status) {
                return $row;
            }
        }

        return null;
    }

    public function reusableArchive(int $orderId, string $compositionHash, \DateTimeImmutable $now): ?Download
    {
        foreach ($this->rows as $row) {
            if ($row->orderId === $orderId && DownloadKindEnum::ZIP === $row->kind && DownloadStatusEnum::READY === $row->status
                && $row->compositionHash === $compositionHash && null !== $row->expiresAt && $row->expiresAt > $now) {
                return $row;
            }
        }

        return null;
    }

    public function claim(string $publicId, int $maxAttempts, \DateTimeImmutable $now, \DateTimeImmutable $leaseUntil): bool
    {
        $row = $this->rows[$publicId] ?? null;
        if (null === $row || DownloadStatusEnum::PENDING !== $row->status || $row->attempts >= $maxAttempts
            || (0 < $row->attempts && (null === $row->nextAttemptAt || $row->nextAttemptAt > $now))) {
            return false;
        }
        $this->replace($row, attempts: $row->attempts + 1, nextAttemptAt: $leaseUntil);

        return true;
    }

    public function markReady(string $publicId, string $archivePath, int $bytes, \DateTimeImmutable $expiresAt): void
    {
        $this->replace($this->rows[$publicId], status: DownloadStatusEnum::READY, archivePath: $archivePath, bytes: $bytes, expiresAt: $expiresAt, nextAttemptAt: null);
    }

    public function markRetry(string $publicId, \DateTimeImmutable $nextAttemptAt): void
    {
        $this->replace($this->rows[$publicId], nextAttemptAt: $nextAttemptAt);
    }

    public function markFailed(string $publicId, string $errorCode, \DateTimeImmutable $expiresAt): void
    {
        $this->replace($this->rows[$publicId], status: DownloadStatusEnum::FAILED, errorCode: $errorCode, expiresAt: $expiresAt, nextAttemptAt: null);
    }

    public function markExpired(string $publicId): void
    {
        $this->replace($this->rows[$publicId], status: DownloadStatusEnum::EXPIRED, archivePath: null);
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        return array_values(array_filter($this->rows, static fn(Download $row): bool => DownloadStatusEnum::PENDING === $row->status
            && null !== $row->nextAttemptAt && $row->nextAttemptAt <= $now));
    }

    public function stale(\DateTimeImmutable $now, int $limit): array
    {
        return array_values(array_filter($this->rows, static fn(Download $row): bool => in_array($row->status, [DownloadStatusEnum::READY, DownloadStatusEnum::FAILED], true)
            && null !== $row->expiresAt && $row->expiresAt <= $now));
    }

    private function replace(
        Download $row,
        ?DownloadStatusEnum $status = null,
        ?string $archivePath = '',
        ?int $bytes = -1,
        ?string $errorCode = null,
        ?int $attempts = null,
        ?\DateTimeImmutable $nextAttemptAt = new \DateTimeImmutable('@0'),
        ?\DateTimeImmutable $expiresAt = new \DateTimeImmutable('@0'),
    ): void {
        $keep = static fn(?\DateTimeImmutable $value, ?\DateTimeImmutable $old): ?\DateTimeImmutable => null !== $value && 0 === $value->getTimestamp() ? $old : $value;
        $this->rows[$row->publicId] = new Download(
            $row->id,
            $row->publicId,
            $row->orderId,
            $row->kind,
            $status ?? $row->status,
            $row->photoIds,
            $row->compositionHash,
            $row->requestHash,
            $row->filename,
            '' === $archivePath ? $row->archivePath : $archivePath,
            -1 === $bytes ? $row->bytes : $bytes,
            $errorCode ?? $row->errorCode,
            $attempts ?? $row->attempts,
            $keep($nextAttemptAt, $row->nextAttemptAt),
            $keep($expiresAt, $row->expiresAt),
        );
    }
}
