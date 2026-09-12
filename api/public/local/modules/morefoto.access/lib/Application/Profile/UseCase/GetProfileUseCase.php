<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Profile\UseCase;

use Morefoto\Access\Application\Profile\Dto\ProfileOutputDto;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;

final readonly class GetProfileUseCase
{
    public function __construct(
        private StaffAuthorization $authorization,
        private PermissionPolicy $policy,
    ) {}

    public function execute(int $userId): ProfileOutputDto
    {
        $context = $this->authorization->context($userId);
        $identity = $context->identity;
        $profile = $context->profile;
        $permissions = [];
        $institutionIds = $this->authorization->institutionIds($profile);
        $groupIds = $this->authorization->groupIds($profile);
        foreach (PermissionEnum::cases() as $permission) {
            if ($this->policy->allows($profile, $permission, institutionIds: $institutionIds, groupIds: $groupIds)) {
                $permissions[] = $permission->value;
            }
        }

        return new ProfileOutputDto(
            id: $identity->id,
            name: $identity->name,
            email: $identity->email,
            role: $profile->role->value,
            active: $profile->active,
            accessRevision: $profile->accessRevision,
            permissions: $permissions,
        );
    }
}
