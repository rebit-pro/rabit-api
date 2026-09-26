<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Database;

use Bitrix\Main\Application;
use Morefoto\Media\Application\Photo\Contract\OriginalFileLockInterface;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;

/** A MySQL named lock is shared by every PHP-FPM worker and consumer that uses the same database. */
final readonly class MysqlOriginalFileLock implements OriginalFileLockInterface
{
    private const int WAIT_SECONDS = 30;

    public function synchronized(string $originalPath, callable $operation): mixed
    {
        // Named locks are limited to 64 characters; the hash keeps the name short and free of quotes.
        $name = "'mf_original:" . sha1($originalPath) . "'";
        $connection = Application::getConnection();
        try {
            $row = $connection->query('SELECT GET_LOCK(' . $name . ',' . self::WAIT_SECONDS . ') AS ACQUIRED')->fetch();
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot lock private original.', 0, $error);
        }
        if (!is_array($row) || 1 !== (int)$row['ACQUIRED']) {
            throw new MediaStorageException('Private original stays locked.');
        }
        try {
            return $operation();
        } finally {
            try {
                $connection->query('SELECT RELEASE_LOCK(' . $name . ')');
            } catch (\Throwable) {
                // The server drops the lock with the connection; a failed release must not hide the operation result.
            }
        }
    }
}
