<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Repository;

use Bitrix\Main\Application;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class AccessStateRepository
{
    /**
     * Owns one local transaction. Participants must not start or commit another transaction.
     * The global row serializes bootstrap now and staff/assignment mutations in subsequent waves.
     *
     * @param callable(): bool $operation true when access changed, false for a no-op
     */
    public function withLock(callable $operation): bool
    {
        return $this->run(function() use ($operation): bool {
            $changed = $operation();
            if ($changed) {
                Application::getConnection()->queryExecute('UPDATE mf_access_state SET assignments_revision = assignments_revision + 1 WHERE id = 1');
            }

            return $changed;
        });
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            if (false === $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id = 1 FOR UPDATE')->fetch()) {
                throw new AccessStorageException('Access state is missing; apply the Access migration.');
            }
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }
    }
}
