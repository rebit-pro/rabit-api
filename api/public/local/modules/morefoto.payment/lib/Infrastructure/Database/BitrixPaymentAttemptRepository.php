<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Payment\Domain\Payment\Exception\PaymentStorageException;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Payment\ValueObject\AttemptOutcome;
use Morefoto\Payment\Domain\Payment\ValueObject\PaymentSearchCriteria;

/**
 * @phpstan-import-type AttemptRecord from PaymentAttemptRepositoryInterface
 */
final readonly class BitrixPaymentAttemptRepository implements PaymentAttemptRepositoryInterface
{
    private const string COLUMNS = "a.ID,a.PUBLIC_ID,a.ORDER_ID,a.ORDER_PUBLIC_ID,a.ORDER_NUMBER,a.ORDER_VERSION,a.INSTITUTION_ID,a.INSTITUTION_NAME,
        a.GROUP_NAME,a.AMOUNT,a.CURRENCY,a.PAYMENT_METHOD,a.PROVIDER,a.SHOP_ID,a.STATUS,a.PRECEDING_ID,a.CLIENT_KEY_HASH,a.REQUEST_HASH,
        a.PROVIDER_KEY,a.PROVIDER_PAYMENT_ID,a.CONFIRMATION_URL,a.CANCEL_REASON,DATE_FORMAT(a.PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,
        a.INCOME_AMOUNT,a.LATE_PAYMENT,a.CHECK_COUNT,DATE_FORMAT(a.NEXT_CHECK_AT,'%Y-%m-%d %H:%i:%s') AS NEXT_CHECK_AT,
        DATE_FORMAT(a.LAST_CHECK_AT,'%Y-%m-%d %H:%i:%s') AS LAST_CHECK_AT,DATE_FORMAT(a.CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT";

    public function insert(array $record): int
    {
        $connection = Application::getConnection();
        $text = $this->text(...);
        try {
            $connection->queryExecute('INSERT INTO mf_payment_attempt(PUBLIC_ID,ORDER_ID,ORDER_PUBLIC_ID,ORDER_NUMBER,ORDER_VERSION,INSTITUTION_ID,
                INSTITUTION_NAME,GROUP_NAME,AMOUNT,CURRENCY,PAYMENT_METHOD,PROVIDER,SHOP_ID,STATUS,ACTIVE_ORDER_ID,PRECEDING_ID,CLIENT_KEY_HASH,
                REQUEST_HASH,PROVIDER_KEY,NEXT_CHECK_AT,CREATED_AT,UPDATED_AT) VALUES(' . implode(',', [
                $text($record['PUBLIC_ID']), $record['ORDER_ID'], $text($record['ORDER_PUBLIC_ID']), $text($record['ORDER_NUMBER']),
                $text($record['ORDER_VERSION']), $record['INSTITUTION_ID'], $text($record['INSTITUTION_NAME']), $text($record['GROUP_NAME']),
                $record['AMOUNT'], "'RUB'", $text($record['PAYMENT_METHOD']), $text($record['PROVIDER']), $text($record['SHOP_ID']), "'unknown'",
                $record['ORDER_ID'], $record['PRECEDING_ID'] ?? 'NULL', $text($record['CLIENT_KEY_HASH']), $text($record['REQUEST_HASH']),
                $text($record['PROVIDER_KEY']), $text($record['NEXT_CHECK_AT']), $text($record['CREATED_AT']), $text($record['CREATED_AT']),
            ]) . ')');

            return (int)$connection->getInsertedId();
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot persist payment attempt.', 0, $error);
        }
    }

    public function find(int $id): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_attempt a WHERE a.ID={$id}"));
    }

    public function findPublic(string $publicId): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . ' FROM mf_payment_attempt a WHERE a.PUBLIC_ID=' . $this->text($publicId)));
    }

    public function findByClientKey(int $orderId, string $clientKeyHash): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_attempt a WHERE a.ORDER_ID={$orderId} AND a.CLIENT_KEY_HASH=" . $this->text($clientKeyHash)));
    }

    public function findByProviderPayment(string $provider, string $providerPaymentId): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . ' FROM mf_payment_attempt a WHERE a.PROVIDER=' . $this->text($provider)
            . ' AND a.PROVIDER_PAYMENT_ID=' . $this->text($providerPaymentId)));
    }

    public function latest(int $orderId): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_attempt a WHERE a.ORDER_ID={$orderId} ORDER BY a.ID DESC LIMIT 1"));
    }

    public function forOrder(int $orderId): array
    {
        return $this->all($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_attempt a WHERE a.ORDER_ID={$orderId} ORDER BY a.ID DESC"));
    }

    public function lock(int $id): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_attempt a WHERE a.ID={$id} FOR UPDATE"));
    }

    public function saveOutcome(int $id, AttemptOutcome $outcome): void
    {
        $text = $this->text(...);
        $open = $outcome->status->isOpen();
        try {
            Application::getConnection()->queryExecute('UPDATE mf_payment_attempt SET STATUS=' . $text($outcome->status->value)
                . ',ACTIVE_ORDER_ID=' . ($open ? 'ORDER_ID' : 'NULL')
                . ',PROVIDER_PAYMENT_ID=' . $text($outcome->providerPaymentId)
                . ',CONFIRMATION_URL=' . $text($outcome->confirmationUrl)
                . ',CANCEL_REASON=' . $text($outcome->cancelReason)
                . ',PAID_AT=' . $text($outcome->paidAt)
                . ',INCOME_AMOUNT=' . ($outcome->incomeAmount ?? 'NULL')
                . ',LATE_PAYMENT=' . (int)$outcome->latePayment
                . ',CHECK_COUNT=CHECK_COUNT+1'
                . ',NEXT_CHECK_AT=' . $text($outcome->nextCheckAt)
                . ',LAST_CHECK_AT=' . $text($outcome->checkedAt)
                . ',UPDATED_AT=UTC_TIMESTAMP()'
                . " WHERE ID={$id}");
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot save payment attempt outcome.', 0, $error);
        }
    }

    public function due(string $now, int $limit): array
    {
        $result = $this->query("SELECT ID FROM mf_payment_attempt WHERE STATUS IN ('unknown','pending') AND NEXT_CHECK_AT<=" . $this->text($now)
            . ' ORDER BY NEXT_CHECK_AT,ID LIMIT ' . max(1, $limit));
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    public function page(PaymentSearchCriteria $criteria, int $limit, int $offset): array
    {
        return $this->all($this->query('SELECT ' . self::COLUMNS . ' FROM mf_payment_attempt a WHERE ' . $this->where($criteria)
            . " ORDER BY a.CREATED_AT DESC,a.ID DESC LIMIT {$limit} OFFSET {$offset}"));
    }

    public function count(PaymentSearchCriteria $criteria): int
    {
        /** @var array{TOTAL: int|string}|false $row */
        $row = $this->query('SELECT COUNT(*) AS TOTAL FROM mf_payment_attempt a WHERE ' . $this->where($criteria))->fetch();

        return false === $row ? 0 : (int)$row['TOTAL'];
    }

    private function where(PaymentSearchCriteria $criteria): string
    {
        $where = '1=1';
        if (null !== $criteria->institutionScope) {
            $where .= [] === $criteria->institutionScope ? ' AND 1=0' : ' AND a.INSTITUTION_ID IN (' . implode(',', array_map('intval', $criteria->institutionScope)) . ')';
        }
        if (null !== $criteria->status) {
            $where .= ' AND a.STATUS=' . $this->text($criteria->status);
        }
        if (null !== $criteria->orderNumber) {
            $where .= ' AND a.ORDER_NUMBER=' . $this->text($criteria->orderNumber);
        }
        if (null !== $criteria->latePayment) {
            $where .= ' AND a.LATE_PAYMENT=' . (int)$criteria->latePayment;
        }
        if (null !== $criteria->createdFrom) {
            $where .= " AND a.CREATED_AT>='" . $criteria->createdFrom->format('Y-m-d H:i:s') . "'";
        }
        if (null !== $criteria->createdBefore) {
            $where .= " AND a.CREATED_AT<'" . $criteria->createdBefore->format('Y-m-d H:i:s') . "'";
        }

        return $where;
    }

    /** @return null|AttemptRecord */
    private function one(Result $result): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $result->fetch();

        return false === $row ? null : $this->record($row);
    }

    /** @return list<AttemptRecord> */
    private function all(Result $result): array
    {
        $records = [];
        while (false !== ($row = $result->fetch())) {
            $records[] = $this->record($row);
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return AttemptRecord
     */
    private function record(array $row): array
    {
        $optional = static fn(string $key): ?string => null === $row[$key] ? null : (string)$row[$key];

        return [
            'ID' => (int)$row['ID'],
            'PUBLIC_ID' => (string)$row['PUBLIC_ID'],
            'ORDER_ID' => (int)$row['ORDER_ID'],
            'ORDER_PUBLIC_ID' => (string)$row['ORDER_PUBLIC_ID'],
            'ORDER_NUMBER' => (string)$row['ORDER_NUMBER'],
            'ORDER_VERSION' => (string)$row['ORDER_VERSION'],
            'INSTITUTION_ID' => (int)$row['INSTITUTION_ID'],
            'INSTITUTION_NAME' => (string)$row['INSTITUTION_NAME'],
            'GROUP_NAME' => (string)$row['GROUP_NAME'],
            'AMOUNT' => (int)$row['AMOUNT'],
            'CURRENCY' => (string)$row['CURRENCY'],
            'PAYMENT_METHOD' => (string)$row['PAYMENT_METHOD'],
            'PROVIDER' => (string)$row['PROVIDER'],
            'SHOP_ID' => (string)$row['SHOP_ID'],
            'STATUS' => (string)$row['STATUS'],
            'PRECEDING_ID' => null === $row['PRECEDING_ID'] ? null : (int)$row['PRECEDING_ID'],
            'CLIENT_KEY_HASH' => (string)$row['CLIENT_KEY_HASH'],
            'REQUEST_HASH' => (string)$row['REQUEST_HASH'],
            'PROVIDER_KEY' => (string)$row['PROVIDER_KEY'],
            'PROVIDER_PAYMENT_ID' => $optional('PROVIDER_PAYMENT_ID'),
            'CONFIRMATION_URL' => $optional('CONFIRMATION_URL'),
            'CANCEL_REASON' => $optional('CANCEL_REASON'),
            'PAID_AT' => $optional('PAID_AT'),
            'INCOME_AMOUNT' => null === $row['INCOME_AMOUNT'] ? null : (int)$row['INCOME_AMOUNT'],
            'LATE_PAYMENT' => 1 === (int)$row['LATE_PAYMENT'],
            'CHECK_COUNT' => (int)$row['CHECK_COUNT'],
            'NEXT_CHECK_AT' => $optional('NEXT_CHECK_AT'),
            'LAST_CHECK_AT' => $optional('LAST_CHECK_AT'),
            'CREATED_AT' => (string)$row['CREATED_AT'],
        ];
    }

    private function text(?string $value): string
    {
        return null === $value ? 'NULL' : "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot read payment attempts.', 0, $error);
        }
    }
}
