<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Service;

use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;

final readonly class PermissionPolicy
{
    /**
     * Scope must come from Access storage and resource ancestry from Organization, never from the request.
     *
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     */
    public function allows(
        StaffProfile $profile,
        PermissionEnum $permission,
        array $institutionIds = [],
        array $groupIds = [],
        ?int $institutionId = null,
        ?int $groupId = null,
    ): bool {
        if (!$profile->isEnabled()) {
            return false;
        }
        if (RoleEnum::ORGANIZER === $profile->role || PermissionEnum::PROFILE_READ === $permission) {
            return true;
        }

        return match ($permission) {
            PermissionEnum::INSTITUTION_READ, PermissionEnum::SHOOT_READ => in_array($profile->role, [RoleEnum::CURATOR, RoleEnum::HEAD], true)
                && $this->inScope($institutionIds, $institutionId),
            PermissionEnum::GROUP_READ => match ($profile->role) {
                RoleEnum::CURATOR, RoleEnum::HEAD => (null === $groupId || null !== $institutionId)
                    && $this->inScope($institutionIds, $institutionId),
                RoleEnum::TEACHER => $this->inScope($groupIds, $groupId),
                default => false,
            },
            default => false,
        };
    }

    /** @param list<int> $ids */
    private function inScope(array $ids, ?int $id): bool
    {
        return null === $id ? [] !== $ids : 0 < $id && in_array($id, $ids, true);
    }
}
