<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Access\Application\Staff\Dto\ListStaffInputDto;
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\AccountStatusEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;

final readonly class StaffManagementRepository
{
    /** @return array{Result,int} */
    public function list(ListStaffInputDto $input): array
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $where = [];
        if ('' !== $input->query) {
            $query = $helper->forSql('%' . $input->query . '%');
            $where[] = "(u.NAME LIKE '{$query}' OR u.EMAIL LIKE '{$query}')";
        }
        if (null !== $input->role) {
            $where[] = "p.UF_ROLE='" . $input->role->value . "'";
        }
        if (null !== $input->active) {
            $where[] = 'p.UF_ACTIVE=' . ($input->active ? '1' : '0');
        }
        if (null !== $input->accountStatus) {
            // Mirrors StaffDirectoryUseCase::status(): a pending account wins, an active one needs access and identity on.
            $pending = 'COALESCE(uf.UF_AUTH_REGISTRATION_PENDING,0)';
            $working = "COALESCE(p.UF_ACTIVE,0)=1 AND u.ACTIVE='Y'";
            $where[] = match ($input->accountStatus) {
                AccountStatusEnum::PENDING => "{$pending}=1",
                AccountStatusEnum::ACTIVE => "{$pending}=0 AND {$working}",
                AccountStatusEnum::BLOCKED => "{$pending}=0 AND NOT ({$working})",
            };
        }
        $condition = [] === $where ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->query('SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_staff_profile p JOIN b_user u ON u.ID=p.UF_USER_ID LEFT JOIN b_uts_user uf ON uf.VALUE_ID=u.ID' . $condition)->fetch();
        $offset = ($input->page - 1) * $input->pageSize;

        return [
            $this->query($this->select() . $condition . " ORDER BY p.UF_USER_ID LIMIT {$input->pageSize} OFFSET {$offset}"),
            (int)($count['TOTAL'] ?? 0),
        ];
    }

    public function all(): Result
    {
        return $this->query($this->select() . ' ORDER BY p.UF_USER_ID');
    }

    public function find(int $userId, bool $lock = false): Result
    {
        if (1 > $userId) {
            throw new \InvalidArgumentException('Positive staff user ID required.');
        }

        return $this->query($this->select() . " WHERE p.UF_USER_ID={$userId}" . ($lock ? ' FOR UPDATE' : ''));
    }

    public function createProfile(int $userId, RoleEnum $role, bool $active): void
    {
        $this->execute(
            'INSERT INTO b_hlbd_mf_staff_profile (UF_USER_ID,UF_ROLE,UF_ACTIVE,UF_REVISION,UF_ACCESS_REVISION,UF_CREATED_AT,UF_UPDATED_AT)'
            . " VALUES ({$userId},'{$role->value}'," . ($active ? '1' : '0') . ',1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())',
        );
    }

    public function updateProfile(int $userId, RoleEnum $role, bool $active, int $revision, int $accessRevision): void
    {
        $this->execute(
            "UPDATE b_hlbd_mf_staff_profile SET UF_ROLE='{$role->value}',UF_ACTIVE=" . ($active ? '1' : '0')
            . ",UF_REVISION={$revision},UF_ACCESS_REVISION={$accessRevision},UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_USER_ID={$userId}",
        );
    }

    public function activeOrganizerCountForUpdate(): int
    {
        $result = $this->query(
            'SELECT p.UF_USER_ID FROM b_hlbd_mf_staff_profile p '
            . 'JOIN b_user u ON u.ID=p.UF_USER_ID LEFT JOIN b_uts_user uf ON uf.VALUE_ID=u.ID '
            . "WHERE p.UF_ROLE='organizer' AND p.UF_ACTIVE=1 AND u.ACTIVE='Y' "
            . 'AND COALESCE(uf.UF_AUTH_REGISTRATION_PENDING,0)=0 ORDER BY p.UF_USER_ID FOR UPDATE',
        );
        $count = 0;
        while (false !== $result->fetch()) {
            ++$count;
        }

        return $count;
    }

    public function recordChange(
        int $userId,
        int $fromRevision,
        int $toRevision,
        int $actorUserId,
        string $operationId,
        string $delta,
    ): void {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $operationId = $helper->forSql($operationId);
        $delta = $helper->forSql($delta);
        $this->execute(
            'INSERT INTO b_hlbd_mf_access_change '
            . '(UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT)'
            . " VALUES ({$userId},{$fromRevision},{$toRevision},{$actorUserId},'{$operationId}','{$delta}',UTC_TIMESTAMP())",
        );
    }

    public function findOperation(int $actor, string $operation, string $key): Result
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $operation = $helper->forSql($operation);
        $key = $helper->forSql($key);

        return $this->query("SELECT payload_hash,result_json FROM mf_staff_operation WHERE actor_id={$actor} AND operation='{$operation}' AND idempotency_key='{$key}'");
    }

    public function saveOperation(int $actor, string $operation, string $key, string $hash, string $result): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $this->execute(sprintf(
            "INSERT INTO mf_staff_operation(actor_id,operation,idempotency_key,payload_hash,result_json,created_at) VALUES(%d,'%s','%s','%s','%s',UTC_TIMESTAMP())",
            $actor,
            $helper->forSql($operation),
            $helper->forSql($key),
            $helper->forSql($hash),
            $helper->forSql($result),
        ));
    }

    /** @param array<string,mixed> $row */
    public function profile(array $row): StaffProfile
    {
        return StaffProfile::fromRow($row)
            ?? throw new AccessStorageException('Staff profile row is unavailable.');
    }

    private function select(): string
    {
        return 'SELECT p.UF_USER_ID,p.UF_ROLE,p.UF_ACTIVE,p.UF_REVISION,p.UF_ACCESS_REVISION,'
            . 'u.NAME,u.EMAIL,u.ACTIVE AS AUTH_ACTIVE,COALESCE(uf.UF_AUTH_REGISTRATION_PENDING,0) AS AUTH_PENDING,'
            . '((SELECT COUNT(*) FROM b_hlbd_mf_institution_assignment ia WHERE ia.UF_USER_ID=p.UF_USER_ID)'
            . '+(SELECT COUNT(*) FROM b_hlbd_mf_group_assignment ga WHERE ga.UF_USER_ID=p.UF_USER_ID)) AS ASSIGNMENT_COUNT '
            . 'FROM b_hlbd_mf_staff_profile p JOIN b_user u ON u.ID=p.UF_USER_ID '
            . 'LEFT JOIN b_uts_user uf ON uf.VALUE_ID=u.ID';
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read staff management data.', 0, $exception);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot save staff management data.', 0, $exception);
        }
    }
}
