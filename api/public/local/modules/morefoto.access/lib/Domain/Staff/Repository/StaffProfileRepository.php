<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Query\Result;
use Morefoto\Access\Domain\Staff\Orm\StaffProfileTable;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class StaffProfileRepository
{
    public function findByUserId(int $userId): Result
    {
        try {
            return StaffProfileTable::query()
                ->setSelect(['UF_USER_ID', 'UF_ROLE', 'UF_ACTIVE', 'UF_REVISION', 'UF_ACCESS_REVISION'])
                ->where('UF_USER_ID', $userId)
                ->setLimit(1)
                ->exec()
            ;
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read staff profile.', 0, $exception);
        }
    }

    /** Must run under the AccessState lock; bootstrap never overwrites an existing profile. */
    public function hasProfiles(): bool
    {
        try {
            return false !== StaffProfileTable::query()->setSelect(['ID'])->setLimit(1)->exec()->fetch();
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot inspect staff profiles.', 0, $exception);
        }
    }

    public function addFirstOrganizer(int $userId): void
    {
        if (0 >= $userId) {
            throw new \InvalidArgumentException('A positive user ID is required.');
        }
        try {
            Application::getConnection()->queryExecute(
                'INSERT INTO b_hlbd_mf_staff_profile'
                . ' (UF_USER_ID, UF_ROLE, UF_ACTIVE, UF_REVISION, UF_ACCESS_REVISION, UF_CREATED_AT, UF_UPDATED_AT)'
                . " VALUES ({$userId}, 'organizer', 1, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            );
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot create the first organizer.', 0, $exception);
        }
    }
}
