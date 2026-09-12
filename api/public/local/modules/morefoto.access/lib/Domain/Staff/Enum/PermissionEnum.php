<?php

declare(strict_types=1);

namespace Morefoto\Access\Domain\Staff\Enum;

/** First Access/Organization slice; later modules add their own explicit actions. */
enum PermissionEnum: string
{
    case PROFILE_READ = 'profile.read';
    case STAFF_MANAGE = 'staff.manage';
    case INSTITUTION_READ = 'institution.read';
    case SHOOT_READ = 'shoot.read';
    case GROUP_READ = 'group.read';
    case ORGANIZATION_MANAGE = 'organization.manage';
}
