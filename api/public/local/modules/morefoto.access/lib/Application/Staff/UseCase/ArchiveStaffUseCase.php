<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\UseCase;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Убирает сотрудника из кабинета по решению организатора: снимает назначения и профиль, закрывает вход и ссылки,
 * но сохраняет учётку и журнал, чтобы история не теряла автора, а повторное добавление по email прислало приглашение.
 * Нельзя убрать себя и последнего активного организатора.
 */
final readonly class ArchiveStaffUseCase
{
    public function __construct(
        private StaffAuthorization $authorization,
        private AccessStateRepository $state,
        private StaffManagementRepository $staff,
        private InstitutionAssignmentRepository $institutions,
        private GroupAssignmentRepository $groups,
        private StaffIdentityGatewayInterface $identities,
    ) {}

    public function execute(int $actorUserId, int $userId): void
    {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);
        if ($actorUserId === $userId) {
            throw new HttpException('CANNOT_ARCHIVE_SELF', 409);
        }

        $this->state->run(function() use ($actorUserId, $userId): void {
            $row = $this->staff->find($userId, true)->fetch();
            if (false === $row) {
                throw new HttpException('STAFF_NOT_FOUND', 404);
            }
            $profile = $this->staff->profile($row);
            if (RoleEnum::ORGANIZER === $profile->role && $profile->active && 1 >= $this->staff->activeOrganizerCountForUpdate()) {
                throw new HttpException('LAST_ORGANIZER', 409);
            }

            $this->institutions->deleteForUser($userId);
            $this->groups->deleteForUser($userId);
            $this->staff->recordChange(
                $userId,
                $profile->revision,
                $profile->revision + 1,
                $actorUserId,
                Uuid::uuid4()->toString(),
                '{"archived":true}',
            );
            $this->staff->deleteProfile($userId);
            $this->institutions->advanceState();
            $this->identities->archive($userId);
        });
    }
}
