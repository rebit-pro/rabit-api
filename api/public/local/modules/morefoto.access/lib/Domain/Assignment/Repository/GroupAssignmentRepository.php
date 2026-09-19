<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Assignment\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class GroupAssignmentRepository
{
    /** @param list<int> $groupIds */
    public function assignments(array $groupIds): Result
    {
        foreach ($groupIds as $id) {
            if (1 > $id) {
                throw new \InvalidArgumentException('Positive group ID required.');
            }
        }
        $ids = [] === $groupIds ? '0' : implode(',', $groupIds);

        return $this->query('SELECT UF_GROUP_ID,UF_USER_ID FROM b_hlbd_mf_group_assignment WHERE UF_GROUP_ID IN (' . $ids . ') ORDER BY UF_GROUP_ID');
    }

    /** @return list<int> */
    public function groupIds(int $userId): array
    {
        if (1 > $userId) {
            throw new \InvalidArgumentException('Positive user ID required.');
        }
        $result = $this->query("SELECT UF_GROUP_ID FROM b_hlbd_mf_group_assignment WHERE UF_USER_ID={$userId} ORDER BY UF_GROUP_ID");
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['UF_GROUP_ID'];
        }

        return $ids;
    }

    public function deleteForUser(int $userId): void
    {
        Application::getConnection()->queryExecute("DELETE FROM b_hlbd_mf_group_assignment WHERE UF_USER_ID={$userId}");
    }

    public function set(int $groupId, ?int $userId): void
    {
        if (1 > $groupId || (null !== $userId && 1 > $userId)) {
            throw new \InvalidArgumentException('Invalid group assignment.');
        }
        try {
            $connection = Application::getConnection();
            if (null === $userId) {
                $connection->queryExecute("DELETE FROM b_hlbd_mf_group_assignment WHERE UF_GROUP_ID={$groupId}");

                return;
            }
            $connection->queryExecute("INSERT INTO b_hlbd_mf_group_assignment (UF_GROUP_ID,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES ({$groupId},{$userId},UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE UF_USER_ID={$userId},UF_UPDATED_AT=UTC_TIMESTAMP()");
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot save group assignment.', 0, $exception);
        }
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read group assignments.', 0, $exception);
        }
    }
}
