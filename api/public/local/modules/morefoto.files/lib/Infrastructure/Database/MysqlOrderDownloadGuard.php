<?php

declare(strict_types=1);

namespace Morefoto\Files\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Files\Application\Files\Contract\OrderDownloadGuardInterface;
use Morefoto\Files\Domain\Download\Exception\FilesStorageException;

/** Именованная блокировка MySQL общая для всех воркеров PHP-FPM; транзакция делает запись ключа и загрузки неделимой. */
final readonly class MysqlOrderDownloadGuard implements OrderDownloadGuardInterface
{
    private const int WAIT_SECONDS = 10;

    public function atomically(int $orderId, callable $operation): mixed
    {
        $name = "'mf_files_order:{$orderId}'";
        $connection = Application::getConnection();
        try {
            $row = $connection->query('SELECT GET_LOCK(' . $name . ',' . self::WAIT_SECONDS . ') AS ACQUIRED')->fetch();
        } catch (\Throwable $error) {
            throw new FilesStorageException('Cannot lock order downloads.', 0, $error);
        }
        if (!is_array($row) || 1 !== (int)$row['ACQUIRED']) {
            throw new FilesStorageException('Order downloads stay locked.');
        }
        try {
            $connection->startTransaction();
            try {
                $result = $operation();
                $connection->commitTransaction();

                return $result;
            } catch (\Throwable $error) {
                $connection->rollbackTransaction();

                throw $error;
            }
        } finally {
            try {
                $connection->query('SELECT RELEASE_LOCK(' . $name . ')');
            } catch (\Throwable) {
                // The server drops the lock with the connection; a failed release must not hide the operation result.
            }
        }
    }
}
