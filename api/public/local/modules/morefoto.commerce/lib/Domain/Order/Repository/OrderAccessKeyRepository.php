<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Repository;

use Bitrix\Main\Application;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;

/** Хранит только SHA-256 личных ключей; сам ключ и его копии сюда не попадают. */
final readonly class OrderAccessKeyRepository
{
    public function insert(int $orderId, string $keyHash, string $issuedAt, string $expiresAt, string $reason, ?int $actorId): void
    {
        $actor = null === $actorId ? 'NULL' : (string)$actorId;
        try {
            Application::getConnection()->queryExecute("INSERT INTO mf_order_access_key(ORDER_ID,KEY_HASH,ISSUED_AT,EXPIRES_AT,ISSUE_REASON,ISSUED_BY_USER_ID)
                VALUES({$orderId},'{$keyHash}','{$issuedAt}','{$expiresAt}','{$reason}',{$actor})");
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot issue order key.', 0, $error);
        }
    }

    /** @return array{ORDER_ID: int|string, EXPIRES_AT: string}|false */
    public function findActive(string $keyHash, string $now): array|false
    {
        try {
            return Application::getConnection()->query("SELECT ORDER_ID,DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT FROM mf_order_access_key
                WHERE KEY_HASH='{$keyHash}' AND REVOKED_AT IS NULL AND EXPIRES_AT>'{$now}'")->fetch();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot resolve order key.', 0, $error);
        }
    }

    public function expiresAt(int $orderId, string $keyHash): ?string
    {
        try {
            $row = Application::getConnection()->query("SELECT DATE_FORMAT(EXPIRES_AT,'%Y-%m-%d %H:%i:%s') AS EXPIRES_AT FROM mf_order_access_key
                WHERE ORDER_ID={$orderId} AND KEY_HASH='{$keyHash}'")->fetch();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot read order key.', 0, $error);
        }

        return false === $row ? null : (string)$row['EXPIRES_AT'];
    }

    /** D07: ключ оплаченного заказа не истекает раньше срока файлов; отозванный ключ не оживает. */
    public function extendUntil(int $orderId, string $until): void
    {
        try {
            Application::getConnection()->queryExecute("UPDATE mf_order_access_key SET EXPIRES_AT=GREATEST(EXPIRES_AT,'{$until}')
                WHERE ORDER_ID={$orderId} AND REVOKED_AT IS NULL");
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot extend order key.', 0, $error);
        }
    }

    public function revokeActive(int $orderId, string $now, string $reason, ?int $actorId): int
    {
        $actor = null === $actorId ? 'NULL' : (string)$actorId;
        try {
            $connection = Application::getConnection();
            $connection->queryExecute("UPDATE mf_order_access_key SET REVOKED_AT='{$now}',REVOKE_REASON='{$reason}',REVOKED_BY_USER_ID={$actor}
                WHERE ORDER_ID={$orderId} AND REVOKED_AT IS NULL");

            return $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot revoke order key.', 0, $error);
        }
    }
}
