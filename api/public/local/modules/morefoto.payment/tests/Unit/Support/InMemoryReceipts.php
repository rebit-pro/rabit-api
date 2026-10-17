<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptCheck;

/**
 * Чеки в памяти с той же уникальностью (попытка, вид), что и таблица.
 *
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final class InMemoryReceipts implements PaymentReceiptRepositoryInterface
{
    /** @var array<int, ReceiptRecord> */
    public array $rows = [];

    public function insert(array $record): bool
    {
        foreach ($this->rows as $row) {
            if ($row['ATTEMPT_ID'] === $record['ATTEMPT_ID'] && $row['KIND'] === $record['KIND']) {
                return false;
            }
        }
        $id = count($this->rows) + 1;
        $this->rows[$id] = $record + [
            'ID' => $id, 'PROVIDER_RECEIPT_ID' => null, 'ERROR' => null, 'FISCAL_DOCUMENT_NUMBER' => null, 'FISCAL_STORAGE_NUMBER' => null,
            'FISCAL_ATTRIBUTE' => null, 'REGISTERED_AT' => null, 'SENT_AT' => null, 'CHECK_COUNT' => 0, 'LAST_CHECK_AT' => null,
        ];

        return true;
    }

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function lock(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function forOrder(int $orderId): array
    {
        return array_values(array_filter($this->rows, static fn(array $row): bool => $row['ORDER_ID'] === $orderId));
    }

    public function forOrderPublicIds(array $orderPublicIds): array
    {
        return array_values(array_filter($this->rows, static fn(array $row): bool => in_array($row['ORDER_PUBLIC_ID'], $orderPublicIds, true)));
    }

    public function paymentStatuses(array $attemptIds): array
    {
        $statuses = [];
        foreach ($this->rows as $row) {
            if ('payment' === $row['KIND'] && in_array($row['ATTEMPT_ID'], $attemptIds, true)) {
                $statuses[$row['ATTEMPT_ID']] = $row['STATUS'];
            }
        }

        return $statuses;
    }

    public function save(int $id, ReceiptCheck $check): void
    {
        $row = $this->rows[$id];
        $this->rows[$id] = [
            'STATUS' => $check->status->value,
            'PROVIDER_RECEIPT_ID' => $check->providerReceiptId ?? $row['PROVIDER_RECEIPT_ID'],
            'ERROR' => $check->error,
            'FISCAL_DOCUMENT_NUMBER' => $check->fiscalDocumentNumber ?? $row['FISCAL_DOCUMENT_NUMBER'],
            'FISCAL_STORAGE_NUMBER' => $check->fiscalStorageNumber ?? $row['FISCAL_STORAGE_NUMBER'],
            'FISCAL_ATTRIBUTE' => $check->fiscalAttribute ?? $row['FISCAL_ATTRIBUTE'],
            'REGISTERED_AT' => $check->registeredAt ?? $row['REGISTERED_AT'],
            'CHECK_COUNT' => $row['CHECK_COUNT'] + 1,
            'NEXT_CHECK_AT' => $check->nextCheckAt,
            'LAST_CHECK_AT' => $check->checkedAt,
        ] + $row;
    }

    public function markSent(int $id, string $now): void
    {
        $this->rows[$id]['STATUS'] = 'pending';
        $this->rows[$id]['SENT_AT'] ??= $now;
    }

    public function due(string $now, int $limit): array
    {
        $due = array_filter($this->rows, static fn(array $row): bool => in_array($row['STATUS'], ['queued', 'pending'], true) && null !== $row['NEXT_CHECK_AT'] && $row['NEXT_CHECK_AT'] <= $now);

        return array_slice(array_keys($due), 0, $limit);
    }

    /** @return ReceiptRecord */
    public function kind(string $kind): array
    {
        foreach ($this->rows as $row) {
            if ($kind === $row['KIND']) {
                return $row;
            }
        }

        throw new \LogicException('No receipt of kind ' . $kind);
    }
}
