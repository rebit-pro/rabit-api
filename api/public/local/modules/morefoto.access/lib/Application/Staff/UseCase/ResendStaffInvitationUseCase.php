<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\UseCase;

use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Staff\Dto\StaffInvitationStateOutputDto;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Повторно отправляет приглашение сотруднику, который ещё не задал пароль: прежняя ссылка перестаёт работать.
 * Доступно организатору с правом управления сотрудниками и только для сотрудника с включённым доступом.
 */
final readonly class ResendStaffInvitationUseCase
{
    public function __construct(
        private StaffAuthorization $authorization,
        private AccessStateRepository $state,
        private StaffManagementRepository $staff,
        private StaffIdentityGatewayInterface $identities,
    ) {}

    public function execute(int $actorUserId, int $userId): StaffInvitationStateOutputDto
    {
        $this->authorization->assertCan($actorUserId, PermissionEnum::STAFF_MANAGE);

        return $this->state->run(function() use ($actorUserId, $userId): StaffInvitationStateOutputDto {
            $row = $this->staff->find($userId, true)->fetch();
            if (false === $row) {
                throw new HttpException('STAFF_NOT_FOUND', 404);
            }
            if (!$this->staff->profile($row)->active) {
                throw new HttpException('INVITATION_NOT_AVAILABLE', 409);
            }
            $invitation = $this->identities->issueInvitation($userId, $actorUserId);

            return new StaffInvitationStateOutputDto($invitation->sentAt, $invitation->expiresAt, $invitation->state);
        });
    }
}
