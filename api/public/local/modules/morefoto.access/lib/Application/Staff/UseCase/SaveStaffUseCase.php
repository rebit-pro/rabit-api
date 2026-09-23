<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\UseCase;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Staff\Contract\AssignmentDirectoryInterface;
use Morefoto\Access\Application\Staff\Dto\StaffMutationInputDto;
use Morefoto\Access\Application\Staff\Dto\StaffMutationOutputDto;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Создаёт или изменяет сотрудника по решению организатора: учётку ожидания, роль, доступ и назначения с проверкой
 * версии и конфликтов. При изменении доступа отзывает сессии, а сотруднику без пароля отправляет приглашение.
 */
final readonly class SaveStaffUseCase
{
    public function __construct(
        private StaffAuthorization $authorization,
        private AccessStateRepository $state,
        private StaffManagementRepository $staff,
        private StaffIdentityGatewayInterface $identities,
        private AssignmentDirectoryInterface $directory,
        private InstitutionAssignmentRepository $institutions,
        private GroupAssignmentRepository $groups,
        private InstitutionAccessInterface $access,
    ) {}

    public function execute(
        int $actorUserId,
        string $bearer,
        ?int $userId,
        StaffMutationInputDto $input,
    ): StaffMutationOutputDto {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
        $operation = null === $userId ? 'create' : 'update:' . $userId;
        $payloadHash = $input->payloadHash();

        return $this->state->run(function() use ($actorUserId, $bearer, $userId, $input, $operation, $payloadHash): StaffMutationOutputDto {
            $stored = $this->staff->findOperation($actorUserId, $operation, $input->key)->fetch();
            if (false !== $stored) {
                if (!hash_equals((string)$stored['payload_hash'], $payloadHash)) {
                    throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
                }
                $data = json_decode((string)$stored['result_json'], true, 16, JSON_THROW_ON_ERROR);

                return new StaffMutationOutputDto(
                    (int)$data['id'],
                    (int)$data['revision'],
                    (int)$data['accessRevision'],
                    (string)$data['assignmentSignature'],
                    (string)$data['accountStatus'],
                );
            }

            $directory = $this->directory->snapshot(true);
            $institutionByPublic = [];
            $institutionIds = [];
            foreach ($directory->institutions as $institution) {
                $institutionByPublic[$institution->id] = $institution->internalId;
                $institutionIds[] = $institution->internalId;
            }
            $groupByPublic = [];
            $groupIds = [];
            foreach ($directory->groups as $group) {
                $groupByPublic[$group->id] = $group->internalId;
                $groupIds[] = $group->internalId;
            }
            $desiredInstitutions = $this->resolve($input->institutionIds, $institutionByPublic);
            $desiredGroups = $this->resolve($input->groupIds, $groupByPublic);
            $this->assertScope($input->role, $desiredInstitutions, $desiredGroups);

            $existing = null;
            if (null !== $userId) {
                $row = $this->staff->find($userId, true)->fetch();
                if (false === $row) {
                    throw new HttpException('STAFF_NOT_FOUND', 404);
                }
                $existing = $this->staff->profile($row);
                if ($existing->revision !== $input->revision) {
                    throw new HttpException('STAFF_VERSION_CONFLICT', 409);
                }
            } elseif (null !== $input->revision) {
                throw new HttpException('INVALID_REVISION', 422);
            }

            $identityByEmail = $this->identities->findByEmailForUpdate($input->email);
            $contactChanged = false;
            $emailChanged = false;
            if (null === $userId) {
                if (null !== $identityByEmail) {
                    $occupied = $this->staff->find($identityByEmail->id, true)->fetch();
                    if (false !== $occupied) {
                        throw new HttpException('EMAIL_OCCUPIED', 409);
                    }
                    $userId = $identityByEmail->id;
                    $identity = $this->identities->updateContact($userId, $input->email, $input->name);
                } else {
                    $identity = $this->identities->createPending($input->email, $input->name);
                    $userId = $identity->id;
                }
            } else {
                if (null !== $identityByEmail && $identityByEmail->id !== $userId) {
                    throw new HttpException('EMAIL_OCCUPIED', 409);
                }
                $identity = $this->identities->lockById($userId);
                if (null === $identity) {
                    throw new HttpException('STAFF_NOT_FOUND', 404);
                }
                $contactChanged = $identity->email !== $input->email || $identity->name !== $input->name;
                $emailChanged = $identity->email !== $input->email;
                if ($identity->email !== $input->email || $identity->name !== $input->name) {
                    $identity = $this->identities->updateContact($userId, $input->email, $input->name);
                }
            }

            $institutionOccupants = [];
            $result = $this->institutions->assignments($institutionIds);
            while (false !== ($row = $result->fetch())) {
                $institutionOccupants[(int)$row['UF_INSTITUTION_ID']][(string)$row['UF_ROLE']] = (int)$row['UF_USER_ID'];
            }
            $groupOccupants = [];
            $result = $this->groups->assignments($groupIds);
            while (false !== ($row = $result->fetch())) {
                $groupOccupants[(int)$row['UF_GROUP_ID']] = (int)$row['UF_USER_ID'];
            }
            $conflicts = [];
            if (in_array($input->role, [RoleEnum::CURATOR, RoleEnum::HEAD], true)) {
                foreach ($desiredInstitutions as $id) {
                    $occupant = $institutionOccupants[$id][$input->role->value] ?? null;
                    if (null !== $occupant && $occupant !== $userId) {
                        $conflicts[$occupant] = true;
                    }
                }
            }
            if (RoleEnum::TEACHER === $input->role) {
                foreach ($desiredGroups as $id) {
                    $occupant = $groupOccupants[$id] ?? null;
                    if (null !== $occupant && $occupant !== $userId) {
                        $conflicts[$occupant] = true;
                    }
                }
            }
            if ([] !== $conflicts && (!$input->replaceAssignments || null === $input->reason || '' === trim($input->reason))) {
                throw new HttpException('ASSIGNMENT_OCCUPIED', 409);
            }
            if ([] !== $conflicts && $this->access->signature() !== $input->assignmentSignature) {
                throw new HttpException('ASSIGNMENTS_CHANGED', 409);
            }

            $affected = array_map('intval', array_keys($conflicts));
            $affected[] = $userId;
            $this->access->lockParticipants($actorUserId, $bearer, $affected);
            foreach ($affected as $affectedId) {
                if ($affectedId !== $userId && false === $this->staff->find($affectedId, true)->fetch()) {
                    throw new HttpException('INVALID_ASSIGNEE', 422);
                }
            }

            if (null !== $existing && RoleEnum::ORGANIZER === $existing->role
                && (RoleEnum::ORGANIZER !== $input->role || !$input->active)
                && 1 >= $this->staff->activeOrganizerCountForUpdate()) {
                throw new HttpException('LAST_ORGANIZER', 409);
            }

            $oldInstitutionIds = null === $existing ? [] : $this->institutions->institutionIds($userId, $existing->role->value);
            $oldGroupIds = null === $existing ? [] : $this->groups->groupIds($userId);
            $assignmentChanged = $oldInstitutionIds !== $desiredInstitutions || $oldGroupIds !== $desiredGroups;
            $profileChanged = null === $existing || $existing->role !== $input->role || $existing->active !== $input->active;
            $changed = $assignmentChanged || $profileChanged || $contactChanged;

            $fromRevision = null === $existing ? 0 : $existing->revision;
            $revision = $fromRevision + 1;
            $accessRevision = (null === $existing ? 0 : $existing->accessRevision) + 1;
            if (null === $existing) {
                $this->staff->createProfile($userId, $input->role, $input->active);
            }

            $this->institutions->deleteForUser($userId);
            $this->groups->deleteForUser($userId);
            if ($input->active && in_array($input->role, [RoleEnum::CURATOR, RoleEnum::HEAD], true)) {
                foreach ($desiredInstitutions as $id) {
                    $this->institutions->set($id, $input->role->value, $userId);
                }
            }
            if ($input->active && RoleEnum::TEACHER === $input->role) {
                foreach ($desiredGroups as $id) {
                    $this->groups->set($id, $userId);
                }
            }

            if (null !== $existing) {
                $this->staff->updateProfile($userId, $input->role, $input->active, $revision, $accessRevision);
            }
            $operationId = $this->operationId($input->key);
            $delta = json_encode([
                'role' => $input->role->value,
                'active' => $input->active,
                'institutionIds' => $input->institutionIds,
                'groupIds' => $input->groupIds,
                'replacedUserIds' => array_map('intval', array_keys($conflicts)),
                'reason' => $input->reason,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->staff->recordChange($userId, $fromRevision, $revision, $actorUserId, $operationId, $delta);

            foreach (array_keys($conflicts) as $displacedId) {
                $displacedRow = $this->staff->find((int)$displacedId, true)->fetch();
                if (false === $displacedRow) {
                    continue;
                }
                $displaced = $this->staff->profile($displacedRow);
                $this->staff->updateProfile(
                    $displaced->userId,
                    $displaced->role ?? throw new HttpException('INVALID_ASSIGNEE', 422),
                    $displaced->active,
                    $displaced->revision + 1,
                    $displaced->accessRevision + 1,
                );
                $this->staff->recordChange(
                    $displaced->userId,
                    $displaced->revision,
                    $displaced->revision + 1,
                    $actorUserId,
                    $operationId,
                    $delta,
                );
                $this->identities->revokeSessions($displaced->userId);
            }
            if ($changed || [] !== $conflicts) {
                $this->institutions->advanceState();
                $this->identities->revokeSessions($userId);
            }
            // A pending identity learns its link by letter: new staff, a reused pending identity or a changed address.
            if ($identity->pending && $input->active && (null === $existing || $emailChanged)) {
                $this->identities->issueInvitation($userId, $actorUserId, force: null !== $existing);
            }
            $signature = $this->access->signature();
            $accountStatus = $identity->pending ? 'pending' : ($input->active && $identity->active ? 'active' : 'blocked');
            $output = new StaffMutationOutputDto($userId, $revision, $accessRevision, $signature, $accountStatus);
            $this->staff->saveOperation($actorUserId, $operation, $input->key, $payloadHash, json_encode($output, JSON_THROW_ON_ERROR));

            return $output;
        });
    }

    /**
     * @param list<string>      $publicIds
     * @param array<string,int> $map
     *
     * @return list<int>
     */
    private function resolve(array $publicIds, array $map): array
    {
        $resolved = [];
        foreach ($publicIds as $id) {
            if (!isset($map[$id])) {
                throw new HttpException('INVALID_ASSIGNMENT', 422);
            }
            $resolved[] = $map[$id];
        }
        sort($resolved, SORT_NUMERIC);

        return array_values(array_unique($resolved));
    }

    private function operationId(string $key): string
    {
        return substr($key, 0, 8) . '-' . substr($key, 8, 4) . '-' . substr($key, 12, 4)
            . '-' . substr($key, 16, 4) . '-' . substr($key, 20, 12);
    }

    /** @param list<int> $institutions @param list<int> $groups */
    private function assertScope(RoleEnum $role, array $institutions, array $groups): void
    {
        if (RoleEnum::ORGANIZER === $role && ([] !== $institutions || [] !== $groups)) {
            throw new HttpException('INVALID_ASSIGNMENT', 422);
        }
        if (in_array($role, [RoleEnum::CURATOR, RoleEnum::HEAD], true) && [] !== $groups) {
            throw new HttpException('INVALID_ASSIGNMENT', 422);
        }
        if (RoleEnum::TEACHER === $role && [] !== $institutions) {
            throw new HttpException('INVALID_ASSIGNMENT', 422);
        }
    }
}
