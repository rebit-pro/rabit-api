<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\UseCase;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Staff\Contract\AssignmentDirectoryInterface;
use Morefoto\Access\Application\Staff\Dto\AssignmentOptionsOutputDto;
use Morefoto\Access\Application\Staff\Dto\ListStaffInputDto;
use Morefoto\Access\Application\Staff\Dto\StaffDetailOutputDto;
use Morefoto\Access\Application\Staff\Dto\StaffOutputDto;
use Morefoto\Access\Application\Staff\Dto\StaffPageOutputDto;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Access\Application\Staff\Dto\StaffInvitationStateOutputDto;
use Morefoto\Access\Application\Avatar\Dto\AvatarOutputDto;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Rebit\Share\Application\Contract\Auth\Dto\StaffInvitationOutputDto;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;

/**
 * Отдаёт организатору справочник сотрудников: страницу списка с фильтрами, карточку с назначениями и данные формы
 * назначений с подписью состояния. Статус учётки, приглашение и аватар показываются без ссылок и токенов доступа.
 */
final readonly class StaffDirectoryUseCase
{
    public function __construct(
        private StaffAuthorization $authorization,
        private StaffManagementRepository $staff,
        private AssignmentDirectoryInterface $directory,
        private InstitutionAssignmentRepository $institutions,
        private GroupAssignmentRepository $groups,
        private InstitutionAccessInterface $access,
        private StaffIdentityGatewayInterface $identities,
        private AvatarOutputMapper $avatars,
    ) {}

    public function list(int $actorUserId, ListStaffInputDto $input): StaffPageOutputDto
    {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
        [$result, $total] = $this->staff->list($input);
        $rows = [];
        while (false !== ($row = $result->fetch())) {
            $rows[] = $row;
        }
        $pending = [];
        foreach ($rows as $row) {
            if (1 === (int)$row['AUTH_PENDING']) {
                $pending[] = (int)$row['UF_USER_ID'];
            }
        }
        $invitations = [] === $pending ? [] : $this->identities->invitations($pending);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->summary($row, $invitations[(int)$row['UF_USER_ID']] ?? null);
        }

        return new StaffPageOutputDto($items, $input->page, $input->pageSize, $total);
    }

    public function get(int $actorUserId, int $userId): StaffDetailOutputDto
    {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
        $row = $this->staff->find($userId)->fetch();
        if (false === $row) {
            throw new HttpException('STAFF_NOT_FOUND', 404);
        }
        $directory = $this->directory->snapshot();
        $institutionMap = [];
        foreach ($directory->institutions as $institution) {
            $institutionMap[$institution->internalId] = $institution->id;
        }
        $groupMap = [];
        foreach ($directory->groups as $group) {
            $groupMap[$group->internalId] = $group->id;
        }
        $institutionIds = [];
        foreach ($this->institutions->institutionIds($userId, (string)$row['UF_ROLE']) as $id) {
            if (isset($institutionMap[$id])) {
                $institutionIds[] = $institutionMap[$id];
            }
        }
        $groupIds = [];
        foreach ($this->groups->groupIds($userId) as $id) {
            if (isset($groupMap[$id])) {
                $groupIds[] = $groupMap[$id];
            }
        }
        $profile = $this->staff->profile($row);

        return new StaffDetailOutputDto(
            id: $profile->userId,
            name: (string)$row['NAME'],
            email: (string)$row['EMAIL'],
            role: $profile->role->value,
            active: $profile->active,
            revision: $profile->revision,
            accessRevision: $profile->accessRevision,
            accountStatus: $this->status($row, $profile->active),
            institutionIds: $institutionIds,
            groupIds: $groupIds,
            assignmentSignature: $this->access->signature(),
            invitation: 1 === (int)$row['AUTH_PENDING'] ? $this->invitation($this->identities->invitations([$profile->userId])[$profile->userId] ?? null) : null,
            avatar: $this->avatar($profile->userId, $row),
        );
    }

    public function options(int $actorUserId): AssignmentOptionsOutputDto
    {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
        $signature = $this->access->signature();
        $directory = $this->directory->snapshot();
        $institutionAssignments = $this->institutions->assignments(array_map(
            static fn($item): int => $item->internalId,
            $directory->institutions,
        ));
        $institutionOccupants = [];
        while (false !== ($row = $institutionAssignments->fetch())) {
            $institutionOccupants[(int)$row['UF_INSTITUTION_ID']][(string)$row['UF_ROLE']] = (int)$row['UF_USER_ID'];
        }
        $groupAssignments = $this->groups->assignments(array_map(
            static fn($item): int => $item->internalId,
            $directory->groups,
        ));
        $groupOccupants = [];
        while (false !== ($row = $groupAssignments->fetch())) {
            $groupOccupants[(int)$row['UF_GROUP_ID']] = (int)$row['UF_USER_ID'];
        }
        $institutions = array_map(
            static fn($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'address' => $item->address,
                'curatorId' => $institutionOccupants[$item->internalId]['curator'] ?? null,
                'headId' => $institutionOccupants[$item->internalId]['head'] ?? null,
            ],
            $directory->institutions,
        );
        $groups = array_map(
            static fn($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'shootId' => $item->shootId,
                'shootName' => $item->shootName,
                'institutionId' => $item->institutionId,
                'institutionName' => $item->institutionName,
                'teacherId' => $groupOccupants[$item->internalId] ?? null,
            ],
            $directory->groups,
        );
        $staff = [];
        $result = $this->staff->all();
        while (false !== ($row = $result->fetch())) {
            $staff[] = $this->summary($row, null);
        }
        if ($signature !== $this->access->signature()) {
            throw new HttpException('ASSIGNMENTS_CHANGED', 409);
        }

        return new AssignmentOptionsOutputDto($institutions, $groups, $staff, $signature);
    }

    /** @param array<string,mixed> $row */
    private function summary(array $row, ?StaffInvitationOutputDto $invitation): StaffOutputDto
    {
        $profile = $this->staff->profile($row);

        return new StaffOutputDto(
            id: $profile->userId,
            name: (string)$row['NAME'],
            email: (string)$row['EMAIL'],
            role: $profile->role->value,
            active: $profile->active,
            revision: $profile->revision,
            accessRevision: $profile->accessRevision,
            accountStatus: $this->status($row, $profile->active),
            assignmentCount: (int)$row['ASSIGNMENT_COUNT'],
            invitation: $this->invitation($invitation),
            avatar: $this->avatar($profile->userId, $row),
        );
    }

    /** @param array<string,mixed> $row */
    private function avatar(int $userId, array $row): ?AvatarOutputDto
    {
        return $this->avatars->map($userId, null === ($row['AVATAR_VERSION'] ?? null) ? null : (int)$row['AVATAR_VERSION']);
    }

    private function invitation(?StaffInvitationOutputDto $invitation): ?StaffInvitationStateOutputDto
    {
        return null === $invitation ? null : new StaffInvitationStateOutputDto($invitation->sentAt, $invitation->expiresAt, $invitation->state);
    }

    /** @param array<string,mixed> $row */
    private function status(array $row, bool $profileActive): string
    {
        if (1 === (int)$row['AUTH_PENDING']) {
            return 'pending';
        }

        return $profileActive && 'Y' === (string)$row['AUTH_ACTIVE'] ? 'active' : 'blocked';
    }
}
