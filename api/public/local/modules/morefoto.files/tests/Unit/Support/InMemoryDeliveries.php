<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit\Support;

use Morefoto\Files\Domain\Delivery\Enum\DeliveryKindEnum;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\ValueObject\FileDelivery;

/** Журнал писем в памяти с теми же уникальностями, что у mf_file_delivery. */
final class InMemoryDeliveries implements DeliveryRepositoryInterface
{
    /** @var array<string, FileDelivery> */
    public array $rows = [];
    /** @var array<string, string> публичный ID → хеш Idempotency-Key */
    public array $keys = [];
    public \DateTimeImmutable $cursorAt;
    public int $cursorOrder = 0;
    /** Чтения журнала: методы помечены impure — каждое чтение видит текущее состояние. */
    public int $reads = 0;

    public function __construct(string $cursor = '2027-02-01 00:00:00')
    {
        $this->cursorAt = new \DateTimeImmutable($cursor, new \DateTimeZone('UTC'));
    }

    public function cursor(): array
    {
        return ['at' => $this->cursorAt, 'orderId' => $this->cursorOrder];
    }

    public function advance(\DateTimeImmutable $at, int $orderId): void
    {
        $this->cursorAt = $at;
        $this->cursorOrder = $orderId;
    }

    public function createAuto(string $publicId, int $orderId, string $recipient, \DateTimeImmutable $now): bool
    {
        foreach ($this->rows as $row) {
            if ($row->orderId === $orderId && DeliveryKindEnum::AUTO === $row->kind) {
                return false;
            }
        }
        $this->rows[$publicId] = new FileDelivery($publicId, $orderId, DeliveryKindEnum::AUTO, DeliveryStatusEnum::HELD, $recipient, null, $now, null);

        return true;
    }

    public function createManual(string $publicId, int $orderId, string $recipient, string $idempotencyHash, \DateTimeImmutable $now): bool
    {
        if (null !== $this->findByIdempotency($orderId, $idempotencyHash)) {
            return false;
        }
        $this->rows[$publicId] = new FileDelivery($publicId, $orderId, DeliveryKindEnum::MANUAL, DeliveryStatusEnum::HELD, $recipient, null, $now, null);
        $this->keys[$publicId] = $idempotencyHash;

        return true;
    }

    public function find(string $publicId): ?FileDelivery
    {
        ++$this->reads;

        return $this->rows[$publicId] ?? null;
    }

    public function findByIdempotency(int $orderId, string $idempotencyHash): ?FileDelivery
    {
        ++$this->reads;
        foreach ($this->keys as $id => $hash) {
            if ($hash === $idempotencyHash && $this->rows[$id]->orderId === $orderId) {
                return $this->rows[$id];
            }
        }

        return null;
    }

    public function latest(int $orderId): ?FileDelivery
    {
        ++$this->reads;
        $latest = null;
        foreach ($this->rows as $row) {
            if ($row->orderId === $orderId && (null === $latest || $row->createdAt >= $latest->createdAt)) {
                $latest = $row;
            }
        }

        return $latest;
    }

    public function countSince(int $orderId, \DateTimeImmutable $since): int
    {
        return count(array_filter($this->rows, static fn(FileDelivery $row): bool => $row->orderId === $orderId && $row->createdAt > $since));
    }

    public function held(int $limit): array
    {
        return array_slice(array_values(array_filter($this->rows, static fn(FileDelivery $row): bool => DeliveryStatusEnum::HELD === $row->status)), 0, $limit);
    }

    public function markQueued(string $publicId, string $operationId, \DateTimeImmutable $now): void
    {
        $row = $this->rows[$publicId];
        $this->rows[$publicId] = new FileDelivery($row->publicId, $row->orderId, $row->kind, DeliveryStatusEnum::QUEUED, $row->recipient, $operationId, $row->createdAt, $now);
    }

    public function markCancelled(string $publicId, \DateTimeImmutable $now): void
    {
        $row = $this->rows[$publicId];
        $this->rows[$publicId] = new FileDelivery($row->publicId, $row->orderId, $row->kind, DeliveryStatusEnum::CANCELLED, $row->recipient, null, $row->createdAt, null);
    }
}
