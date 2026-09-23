<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Bitrix\Main\ORM\Query\Result;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** @internal */
final class TeacherAuthorizationTest extends TestCase
{
    public function testProfileReportsOnlyActualGroupScope(): void
    {
        $result = (new GetProfileUseCase($this->authorization([100]), new PermissionPolicy(), $this->createStub(StaffAvatarRepositoryInterface::class), new AvatarOutputMapper()))->execute(21);
        self::assertSame(['profile.read', 'group.read'], $result->permissions);
    }

    public function testTeacherCanReadAssignedGroup(): void
    {
        $this->authorization([100])->assertCan(21, PermissionEnum::GROUP_READ, institutionId: 10, groupId: 100);
        $this->addToAssertionCount(1);
    }

    public function testTeacherCannotReadForeignGroupThroughInstitution(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->authorization([100])->assertCan(21, PermissionEnum::GROUP_READ, institutionId: 10, groupId: 101);
    }

    public function testUnassignedTeacherKeepsOnlyProfile(): void
    {
        $result = (new GetProfileUseCase($this->authorization([]), new PermissionPolicy(), $this->createStub(StaffAvatarRepositoryInterface::class), new AvatarOutputMapper()))->execute(21);
        self::assertSame(['profile.read'], $result->permissions);
    }

    public function testAssignmentCannotGrantManagement(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $this->authorization([100])->assertCan(21, PermissionEnum::ORGANIZATION_MANAGE, institutionId: 10, groupId: 100);
    }

    /** @param list<int> $groupIds */
    private function authorization(array $groupIds): StaffAuthorization
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(['UF_USER_ID' => 21, 'UF_ROLE' => 'teacher', 'UF_ACTIVE' => 1, 'UF_REVISION' => 4, 'UF_ACCESS_REVISION' => 3]);
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturn($result);
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn(new IdentityOutputDto(21, 'Teacher', 'teacher@example.invalid'));
        $groups = $this->createMock(GroupAssignmentRepository::class);
        $groups->expects(self::once())->method('groupIds')->with(21)->willReturn($groupIds);

        return new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $this->createStub(InstitutionAssignmentRepository::class), $groups);
    }
}
