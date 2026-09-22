<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum as Permission;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum as Role;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PermissionPolicyTest extends TestCase
{
    #[DataProvider('scopes')]
    public function testAcceptedScopeMatrix(Role $role, Permission $permission, ?int $institution, ?int $group, bool $expected): void
    {
        $profile = new StaffProfile(10, $role, true, 1, 1);
        self::assertSame($expected, (new PermissionPolicy())->allows($profile, $permission, [11], [21], $institution, $group));
    }

    public static function scopes(): iterable
    {
        yield 'organizer reads any institution' => [Role::ORGANIZER, Permission::INSTITUTION_READ, 99, null, true];
        yield 'organizer manages staff' => [Role::ORGANIZER, Permission::STAFF_MANAGE, null, null, true];
        yield 'organizer manages private media' => [Role::ORGANIZER, Permission::MEDIA_MANAGE, 99, 999, true];
        yield 'curator cannot manage private media' => [Role::CURATOR, Permission::MEDIA_MANAGE, 11, 21, false];
        yield 'teacher cannot manage private media' => [Role::TEACHER, Permission::MEDIA_MANAGE, 11, 21, false];
        yield 'curator own institution' => [Role::CURATOR, Permission::INSTITUTION_READ, 11, null, true];
        yield 'group without verified ancestry' => [Role::CURATOR, Permission::GROUP_READ, null, 21, false];
        yield 'curator foreign institution' => [Role::CURATOR, Permission::INSTITUTION_READ, 12, null, false];
        yield 'head own shoot ancestry' => [Role::HEAD, Permission::SHOOT_READ, 11, null, true];
        yield 'head foreign shoot ancestry' => [Role::HEAD, Permission::SHOOT_READ, 12, null, false];
        yield 'curator group through institution' => [Role::CURATOR, Permission::GROUP_READ, 11, 22, true];
        yield 'curator cannot borrow teacher assignment' => [Role::CURATOR, Permission::GROUP_READ, 12, 21, false];
        yield 'teacher assigned group' => [Role::TEACHER, Permission::GROUP_READ, 12, 21, true];
        yield 'teacher foreign group' => [Role::TEACHER, Permission::GROUP_READ, 11, 22, false];
        yield 'teacher cannot read institution directly' => [Role::TEACHER, Permission::INSTITUTION_READ, 11, 21, false];
        yield 'teacher cannot list shoots' => [Role::TEACHER, Permission::SHOOT_READ, 11, 21, false];
        yield 'head cannot edit organization' => [Role::HEAD, Permission::ORGANIZATION_MANAGE, 11, null, false];
        yield 'curator cannot manage staff' => [Role::CURATOR, Permission::STAFF_MANAGE, null, null, false];
        yield 'curator scoped list' => [Role::CURATOR, Permission::INSTITUTION_READ, null, null, true];
        yield 'invalid resource ID' => [Role::CURATOR, Permission::INSTITUTION_READ, 0, null, false];
        yield 'organizer reads any order' => [Role::ORGANIZER, Permission::ORDER_READ, 99, null, true];
        yield 'curator reads orders of own institution' => [Role::CURATOR, Permission::ORDER_READ, 11, null, true];
        yield 'curator scoped order list' => [Role::CURATOR, Permission::ORDER_READ, null, null, true];
        yield 'curator cannot read foreign orders' => [Role::CURATOR, Permission::ORDER_READ, 12, null, false];
        yield 'head cannot read orders' => [Role::HEAD, Permission::ORDER_READ, 11, null, false];
        yield 'teacher cannot read orders' => [Role::TEACHER, Permission::ORDER_READ, 11, 21, false];
    }

    public function testEmptyScopeNeverGrantsResourceAccess(): void
    {
        $policy = new PermissionPolicy();
        foreach ([Role::CURATOR, Role::HEAD, Role::TEACHER] as $role) {
            $profile = new StaffProfile(10, $role, true, 1, 1);
            self::assertTrue($policy->allows($profile, Permission::PROFILE_READ));
            foreach ([Permission::INSTITUTION_READ, Permission::SHOOT_READ, Permission::GROUP_READ, Permission::ORDER_READ] as $permission) {
                self::assertFalse($policy->allows($profile, $permission));
                self::assertFalse($policy->allows($profile, $permission, institutionId: 11, groupId: 21));
            }
        }
    }

    public function testInvalidProfilesDenyEveryAction(): void
    {
        foreach ([
            new StaffProfile(1, null, true, 1, 1),
            new StaffProfile(1, Role::ORGANIZER, false, 1, 1),
            new StaffProfile(1, Role::ORGANIZER, true, 0, 1),
            new StaffProfile(1, Role::ORGANIZER, true, 1, 0),
        ] as $profile) {
            foreach (Permission::cases() as $permission) {
                self::assertFalse((new PermissionPolicy())->allows($profile, $permission, [11], [21]));
            }
        }
    }
}
