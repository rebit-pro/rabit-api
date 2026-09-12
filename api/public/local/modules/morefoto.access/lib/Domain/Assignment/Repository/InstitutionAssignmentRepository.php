<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Assignment\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class InstitutionAssignmentRepository
{
    public function revision(bool $lock = false): int
    {
        $row = $this->query('SELECT assignments_revision FROM mf_access_state WHERE id=1' . ($lock ? ' FOR UPDATE' : ''))->fetch();
        if (false === $row || 1 > (int)$row['assignments_revision']) {
            throw new AccessStorageException('Access state is missing.');
        }

        return (int)$row['assignments_revision'];
    }

    /** @param list<int> $ids */
    public function assignments(array $ids): Result
    {
        return $this->query('SELECT UF_INSTITUTION_ID, UF_ROLE, UF_USER_ID FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID IN (' . $this->ids($ids) . ') ORDER BY UF_INSTITUTION_ID, UF_ROLE');
    }

    /** @return list<int> */
    public function institutionIds(int $userId, string $role): array
    {
        if (!in_array($role, ['curator', 'head'], true)) {
            return [];
        }
        $result = $this->query("SELECT UF_INSTITUTION_ID FROM b_hlbd_mf_institution_assignment WHERE UF_USER_ID={$userId} AND UF_ROLE='{$role}' ORDER BY UF_INSTITUTION_ID");
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['UF_INSTITUTION_ID'];
        }

        return $ids;
    }

    /** @param list<int> $userIds */
    public function lockProfiles(array $userIds): void
    {
        $this->query('SELECT UF_USER_ID FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID IN (' . $this->ids($userIds) . ') ORDER BY UF_USER_ID FOR UPDATE');
    }

    public function set(int $institutionId, string $role, ?int $userId): void
    {
        if (!in_array($role, ['curator', 'head'], true) || 1 > $institutionId || (null !== $userId && 1 > $userId)) {
            throw new \InvalidArgumentException('Invalid assignment.');
        }
        $connection = Application::getConnection();
        if (null === $userId) {
            $connection->queryExecute("DELETE FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID={$institutionId} AND UF_ROLE='{$role}'");

            return;
        }
        $connection->queryExecute("INSERT INTO b_hlbd_mf_institution_assignment (UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES ({$institutionId},'{$role}',{$userId},UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE UF_USER_ID={$userId}, UF_UPDATED_AT=UTC_TIMESTAMP()");
    }

    public function advanceState(): void
    {
        Application::getConnection()->queryExecute('UPDATE mf_access_state SET assignments_revision=assignments_revision+1 WHERE id=1');
    }

    public function advanceUser(int $userId, int $revision, int $actorUserId, string $operationId, string $delta): void
    {
        $c = Application::getConnection();
        $h = $c->getSqlHelper();
        $c->queryExecute("UPDATE b_hlbd_mf_staff_profile SET UF_REVISION=UF_REVISION+1,UF_ACCESS_REVISION=UF_ACCESS_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_USER_ID={$userId}");
        $next = $revision + 1;
        $delta = $h->forSql($delta);
        $operationId = $h->forSql($operationId);
        $c->queryExecute("INSERT INTO b_hlbd_mf_access_change (UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT) VALUES ({$userId},{$revision},{$next},{$actorUserId},'{$operationId}','{$delta}',UTC_TIMESTAMP())");
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read institution assignments.', 0, $exception);
        }
    }

    /** @param list<int> $ids */
    private function ids(array $ids): string
    {
        if ([] === $ids) {
            return '0';
        }
        foreach ($ids as $id) {
            if (1 > $id) {
                throw new \InvalidArgumentException('Positive ID required.');
            }
        }

        return implode(',', $ids);
    }
}
