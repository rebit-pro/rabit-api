<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Authorization\Service;

use Morefoto\Access\Application\Authorization\Dto\StaffContextOutputDto;
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Единая точка решения о доступе сотрудника: при каждом запросе заново читает identity, профиль и назначения
 * и проверяет право по PermissionPolicy. Отказ сразу несёт код контракта ошибок: UNAUTHORIZED, FORBIDDEN
 * или NOT_FOUND для скрытого чужого учреждения или группы.
 */
final readonly class StaffAuthorization
{
    public function __construct(
        private StaffProfileRepository $profiles,
        private IdentityGatewayInterface $identities,
        private PermissionPolicy $policy,
        private InstitutionAssignmentRepository $assignments,
        private GroupAssignmentRepository $groups,
    ) {}

    /** Re-read every request; singleton services never retain a user's role or scope. */
    public function context(int $userId): StaffContextOutputDto
    {
        $identity = $this->identities->findActive($userId);
        if (null === $identity) {
            throw new HttpException('UNAUTHORIZED', 401);
        }
        $profile = StaffProfile::fromRow($this->profiles->findByUserId($userId)->fetch());
        if (null === $profile || !$profile->isEnabled()) {
            throw new HttpException('FORBIDDEN', 403);
        }

        return new StaffContextOutputDto($identity, $profile);
    }

    /** @return list<int> */
    public function institutionIds(StaffProfile $profile): array
    {
        return $this->assignments->institutionIds($profile->userId, $profile->role->value);
    }

    /** @return list<int> */
    public function groupIds(StaffProfile $profile): array
    {
        return RoleEnum::TEACHER === $profile->role ? $this->groups->groupIds($profile->userId) : [];
    }

    public function assertCan(int $userId, PermissionEnum $permission, ?int $institutionId = null, ?int $groupId = null): void
    {
        $context = $this->context($userId);
        if (!$this->policy->allows($context->profile, $permission, institutionIds: $this->institutionIds($context->profile), groupIds: $this->groupIds($context->profile), institutionId: $institutionId, groupId: $groupId)) {
            $scoped = in_array($permission, [PermissionEnum::INSTITUTION_READ, PermissionEnum::SHOOT_READ, PermissionEnum::GROUP_READ], true);
            $status = $scoped && (null !== $institutionId || null !== $groupId) ? 404 : 403;
            throw new HttpException(404 === $status ? 'NOT_FOUND' : 'FORBIDDEN', $status);
        }
    }
}
