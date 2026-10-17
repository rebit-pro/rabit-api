<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Payment\Domain\Payment\Exception\PaymentStorageException;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptCheck;

/**
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final readonly class BitrixPaymentReceiptRepository implements PaymentReceiptRepositoryInterface
{
    private const string COLUMNS = "r.ID,r.PUBLIC_ID,r.KIND,r.ATTEMPT_ID,r.ORDER_ID,r.ORDER_PUBLIC_ID,r.PROVIDER,r.PROVIDER_PAYMENT_ID,r.PROVIDER_KEY,
        r.PROVIDER_RECEIPT_ID,r.AMOUNT,r.ITEMS,r.STATUS,r.ERROR,r.FISCAL_DOCUMENT_NUMBER,r.FISCAL_STORAGE_NUMBER,r.FISCAL_ATTRIBUTE,
        DATE_FORMAT(r.REGISTERED_AT,'%Y-%m-%d %H:%i:%s') AS REGISTERED_AT,DATE_FORMAT(r.HANDED_AT,'%Y-%m-%d %H:%i:%s') AS HANDED_AT,r.HANDED_BY,
        DATE_FORMAT(r.SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,
        r.CHECK_COUNT,DATE_FORMAT(r.NEXT_CHECK_AT,'%Y-%m-%d %H:%i:%s') AS NEXT_CHECK_AT,DATE_FORMAT(r.LAST_CHECK_AT,'%Y-%m-%d %H:%i:%s') AS LAST_CHECK_AT,
        DATE_FORMAT(r.CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT";

    public function insert(array $record): bool
    {
        $connection = Application::getConnection();
        $text = $this->text(...);
        try {
            // The unique (attempt, kind) pair makes a repeated payment confirmation or handover a no-op.
            $connection->queryExecute('INSERT IGNORE INTO mf_payment_receipt(PUBLIC_ID,KIND,ATTEMPT_ID,ORDER_ID,ORDER_PUBLIC_ID,PROVIDER,
                PROVIDER_PAYMENT_ID,PROVIDER_KEY,AMOUNT,ITEMS,STATUS,HANDED_AT,HANDED_BY,NEXT_CHECK_AT,CREATED_AT,UPDATED_AT) VALUES(' . implode(',', [
                $text($record['PUBLIC_ID']), $text($record['KIND']), $record['ATTEMPT_ID'], $record['ORDER_ID'], $text($record['ORDER_PUBLIC_ID']),
                $text($record['PROVIDER']), $text($record['PROVIDER_PAYMENT_ID']), $text($record['PROVIDER_KEY']), $record['AMOUNT'],
                $text($record['ITEMS']), $text($record['STATUS']), $text($record['HANDED_AT']), $record['HANDED_BY'] ?? 'NULL',
                $text($record['NEXT_CHECK_AT']), $text($record['CREATED_AT']), $text($record['CREATED_AT']),
            ]) . ')');

            return 1 === $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot persist payment receipt.', 0, $error);
        }
    }

    public function find(int $id): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_receipt r WHERE r.ID={$id}"));
    }

    public function lock(int $id): ?array
    {
        return $this->one($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_receipt r WHERE r.ID={$id} FOR UPDATE"));
    }

    public function forOrder(int $orderId): array
    {
        return $this->all($this->query('SELECT ' . self::COLUMNS . " FROM mf_payment_receipt r WHERE r.ORDER_ID={$orderId} ORDER BY r.ID"));
    }

    public function forOrderPublicIds(array $orderPublicIds): array
    {
        if ([] === $orderPublicIds) {
            return [];
        }

        return $this->all($this->query('SELECT ' . self::COLUMNS . ' FROM mf_payment_receipt r WHERE r.ORDER_PUBLIC_ID IN ('
            . implode(',', array_map($this->text(...), $orderPublicIds)) . ') ORDER BY r.ID'));
    }

    public function paymentStatuses(array $attemptIds): array
    {
        if ([] === $attemptIds) {
            return [];
        }
        $result = $this->query("SELECT ATTEMPT_ID,STATUS FROM mf_payment_receipt WHERE KIND='payment' AND ATTEMPT_ID IN ("
            . implode(',', array_map('intval', $attemptIds)) . ')');
        $statuses = [];
        while (false !== ($row = $result->fetch())) {
            $statuses[(int)$row['ATTEMPT_ID']] = (string)$row['STATUS'];
        }

        return $statuses;
    }

    public function save(int $id, ReceiptCheck $check): void
    {
        $text = $this->text(...);
        try {
            // Fiscal requisites, once received, are never erased by a later failed check.
            Application::getConnection()->queryExecute('UPDATE mf_payment_receipt SET STATUS=' . $text($check->status->value)
                . ',PROVIDER_RECEIPT_ID=COALESCE(' . $text($check->providerReceiptId) . ',PROVIDER_RECEIPT_ID)'
                . ',ERROR=' . $text($check->error)
                . ',FISCAL_DOCUMENT_NUMBER=COALESCE(' . $text($check->fiscalDocumentNumber) . ',FISCAL_DOCUMENT_NUMBER)'
                . ',FISCAL_STORAGE_NUMBER=COALESCE(' . $text($check->fiscalStorageNumber) . ',FISCAL_STORAGE_NUMBER)'
                . ',FISCAL_ATTRIBUTE=COALESCE(' . $text($check->fiscalAttribute) . ',FISCAL_ATTRIBUTE)'
                . ',REGISTERED_AT=COALESCE(' . $text($check->registeredAt) . ',REGISTERED_AT)'
                . ',CHECK_COUNT=CHECK_COUNT+1'
                . ',NEXT_CHECK_AT=' . $text($check->nextCheckAt)
                . ',LAST_CHECK_AT=' . $text($check->checkedAt)
                . ',UPDATED_AT=UTC_TIMESTAMP()'
                . " WHERE ID={$id}");
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot save payment receipt check.', 0, $error);
        }
    }

    public function markSent(int $id, string $now): void
    {
        try {
            Application::getConnection()->queryExecute("UPDATE mf_payment_receipt SET STATUS='pending',SENT_AT=COALESCE(SENT_AT," . $this->text($now)
                . "),UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id}");
        } catch (\Throwable $error) {
            throw new PaymentStorageException('Cannot mark payment receipt as sent.', 0, $error);
        }
    }

    public function due(string $now, int $limit): array
    {
        $result = $this->query("SELECT ID FROM mf_payment_receipt WHERE STATUS IN ('queued','pending') AND NEXT_CHECK_AT<=" . $this->text($now)
            . ' ORDER BY NEXT_CHECK_AT,ID LIMIT ' . max(1, $limit));
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    /** @return null|ReceiptRecord */
    private function one(Result $result): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $result->fetch();

        return false === $row ? null : $this->record($row);
    }

    /** @return list<ReceiptRecord> */
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
     * @return ReceiptRecord
     */
    private function record(array $row): array
    {
        $optional = static fn(string $key): ?string => null === $row[$key] ? null : (string)$row[$key];

        return [
            'ID' => (int)$row['ID'],
            'PUBLIC_ID' => (string)$row['PUBLIC_ID'],
            'KIND' => (string)$row['KIND'],
            'ATTEMPT_ID' => (int)$row['ATTEMPT_ID'],
            'ORDER_ID' => (int)$row['ORDER_ID'],
            'ORDER_PUBLIC_ID' => (string)$row['ORDER_PUBLIC_ID'],
            'PROVIDER' => (string)$row['PROVIDER'],
            'PROVIDER_PAYMENT_ID' => (string)$row['PROVIDER_PAYMENT_ID'],
            'PROVIDER_KEY' => (string)$row['PROVIDER_KEY'],
            'PROVIDER_RECEIPT_ID' => $optional('PROVIDER_RECEIPT_ID'),
            'AMOUNT' => (int)$row['AMOUNT'],
            'ITEMS' => (string)$row['ITEMS'],
            'STATUS' => (string)$row['STATUS'],
            'ERROR' => $optional('ERROR'),
            'FISCAL_DOCUMENT_NUMBER' => $optional('FISCAL_DOCUMENT_NUMBER'),
            'FISCAL_STORAGE_NUMBER' => $optional('FISCAL_STORAGE_NUMBER'),
            'FISCAL_ATTRIBUTE' => $optional('FISCAL_ATTRIBUTE'),
            'REGISTERED_AT' => $optional('REGISTERED_AT'),
            'HANDED_AT' => $optional('HANDED_AT'),
            'HANDED_BY' => null === $row['HANDED_BY'] ? null : (int)$row['HANDED_BY'],
            'SENT_AT' => $optional('SENT_AT'),
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
            throw new PaymentStorageException('Cannot read payment receipts.', 0, $error);
        }
    }
}
