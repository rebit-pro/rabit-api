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
    case CATALOG_MANAGE = 'catalog.manage';
    case MEDIA_MANAGE = 'media.manage';
    /** D4: viewing the photos of an institution and correcting the markup of its transmitted group while sales are paused. */
    case MEDIA_CORRECT = 'media.correct';
    case ORDER_READ = 'order.read';
}
