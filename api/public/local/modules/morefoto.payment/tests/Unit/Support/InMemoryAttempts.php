<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit\Support;

use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\ValueObject\AttemptOutcome;
use Morefoto\Payment\Domain\Payment\ValueObject\PaymentSearchCriteria;

/**
 * Хранилище попыток в памяти с той же уникальностью открытой попытки заказа, что и таблица.
 *
 * @phpstan-import-type AttemptRecord from PaymentAttemptRepositoryInterface
 */
final class InMemoryAttempts implements PaymentAttemptRepositoryInterface
{
    /** @var array<int, AttemptRecord> */
    public array $rows = [];
    public int $locks = 0;
    /** Runs right before a lock returns: simulates a concurrent check that committed first. */
    public ?\Closure $beforeLock = null;

    public function insert(array $record): int
    {
        foreach ($this->rows as $row) {
            if ($row['ORDER_ID'] === $record['ORDER_ID'] && in_array($row['STATUS'], ['unknown', 'pending'], true)) {
                throw new \RuntimeException('Duplicate open attempt.');
            }
        }
        $id = count($this->rows) + 1;
        $this->rows[$id] = $record + [
            'ID' => $id, 'CURRENCY' => 'RUB', 'STATUS' => 'unknown', 'PROVIDER_PAYMENT_ID' => null, 'CONFIRMATION_URL' => null,
            'CANCEL_REASON' => null, 'PAID_AT' => null, 'INCOME_AMOUNT' => null, 'LATE_PAYMENT' => false, 'CHECK_COUNT' => 0, 'LAST_CHECK_AT' => null,
        ];

        return $id;
    }

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findPublic(string $publicId): ?array
    {
        return $this->first(static fn(array $row): bool => $row['PUBLIC_ID'] === $publicId);
    }

    public function findByClientKey(int $orderId, string $clientKeyHash): ?array
    {
        return $this->first(static fn(array $row): bool => $row['ORDER_ID'] === $orderId && $row['CLIENT_KEY_HASH'] === $clientKeyHash);
    }

    public function findByProviderPayment(string $provider, string $providerPaymentId): ?array
    {
        return $this->first(static fn(array $row): bool => $row['PROVIDER'] === $provider && $row['PROVIDER_PAYMENT_ID'] === $providerPaymentId);
    }

    public function latest(int $orderId): ?array
    {
        return $this->forOrder($orderId)[0] ?? null;
    }

    public function forOrder(int $orderId): array
    {
        $rows = array_values(array_filter($this->rows, static fn(array $row): bool => $row['ORDER_ID'] === $orderId));

        return array_reverse($rows);
    }

    public function lock(int $id): ?array
    {
        ++$this->locks;
        if (null !== $this->beforeLock) {
            ($this->beforeLock)($this);
            $this->beforeLock = null;
        }

        return $this->find($id);
    }

    public function saveOutcome(int $id, AttemptOutcome $outcome): void
    {
        $this->rows[$id] = array_replace($this->rows[$id], [
            'STATUS' => $outcome->status->value,
            'PROVIDER_PAYMENT_ID' => $outcome->providerPaymentId,
            'CONFIRMATION_URL' => $outcome->confirmationUrl,
            'CANCEL_REASON' => $outcome->cancelReason,
            'PAID_AT' => $outcome->paidAt,
            'INCOME_AMOUNT' => $outcome->incomeAmount,
            'LATE_PAYMENT' => $outcome->latePayment,
            'CHECK_COUNT' => $this->rows[$id]['CHECK_COUNT'] + 1,
            'NEXT_CHECK_AT' => $outcome->nextCheckAt,
            'LAST_CHECK_AT' => $outcome->checkedAt,
        ]);
    }

    public function due(string $now, int $limit): array
    {
        $due = array_filter($this->rows, static fn(array $row): bool => in_array($row['STATUS'], ['unknown', 'pending'], true)
            && null !== $row['NEXT_CHECK_AT'] && $row['NEXT_CHECK_AT'] <= $now);

        return array_slice(array_keys($due), 0, $limit);
    }

    public function page(PaymentSearchCriteria $criteria, int $limit, int $offset): array
    {
        return array_slice($this->matching($criteria), $offset, $limit);
    }

    public function count(PaymentSearchCriteria $criteria): int
    {
        return count($this->matching($criteria));
    }

    /** @return list<AttemptRecord> */
    private function matching(PaymentSearchCriteria $criteria): array
    {
        return array_values(array_reverse(array_filter($this->rows, static fn(array $row): bool => (null === $criteria->institutionScope || in_array($row['INSTITUTION_ID'], $criteria->institutionScope, true))
            && (null === $criteria->status || $row['STATUS'] === $criteria->status))));
    }

    /**
     * @param callable(AttemptRecord): bool $filter
     *
     * @return null|AttemptRecord
     */
    private function first(callable $filter): ?array
    {
        foreach ($this->rows as $row) {
            if ($filter($row)) {
                return $row;
            }
        }

        return null;
    }
}
