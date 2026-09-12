<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class CatalogAccessRepository
{
    /** Caller owns the transaction. Shared state lock excludes staff/assignment changes. */
    public function lockProfile(int $userId): Result
    {
        try {
            $connection = Application::getConnection();
            if (false === $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id = 1 LOCK IN SHARE MODE')->fetch()) {
                throw new AccessStorageException('Access state is unavailable.');
            }

            return $connection->query('SELECT UF_USER_ID, UF_ROLE, UF_ACTIVE, UF_REVISION, UF_ACCESS_REVISION FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID = ' . $userId . ' FOR UPDATE');
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot lock catalogue access.', 0, $exception);
        }
    }
}
