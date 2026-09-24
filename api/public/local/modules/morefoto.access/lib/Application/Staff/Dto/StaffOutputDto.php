<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Morefoto\Access\Application\Avatar\Dto\AvatarOutputDto;

final readonly class StaffOutputDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public bool $active,
        public int $revision,
        public int $accessRevision,
        public string $accountStatus,
        public int $assignmentCount,
        public ?StaffInvitationStateOutputDto $invitation = null,
        public ?AvatarOutputDto $avatar = null,
    ) {}
}
