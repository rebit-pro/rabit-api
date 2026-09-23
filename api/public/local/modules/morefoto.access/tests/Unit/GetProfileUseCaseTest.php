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
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class GetProfileUseCaseTest extends TestCase
{
    public function testProfileCombinesIdentityAndAccessWithoutLeakingSecrets(): void
    {
        $profiles = $this->createMock(StaffProfileRepository::class);
        $profiles->expects(self::once())->method('findByUserId')->with(10)->willReturn($this->queryResult(new StaffProfile(10, RoleEnum::TEACHER, true, 3, 2)));
        $identities = $this->createMock(IdentityGatewayInterface::class);
        $identities->expects(self::once())->method('findActive')->with(10)->willReturn(new IdentityOutputDto(10, 'Teacher', 'teacher@example.invalid'));
        $result = (new GetProfileUseCase(new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $this->createStub(InstitutionAssignmentRepository::class), $this->createStub(GroupAssignmentRepository::class)), new PermissionPolicy(), $this->noAvatars(), new AvatarOutputMapper()))->execute(10);
        self::assertSame([
            'id' => 10, 'name' => 'Teacher', 'email' => 'teacher@example.invalid', 'role' => 'teacher',
            'active' => true, 'accessRevision' => 2, 'permissions' => ['profile.read'], 'avatar' => null,
        ], get_object_vars($result));
    }

    #[DataProvider('deniedProfiles')]
    public function testValidIdentityDoesNotImplyStaffAccess(?StaffProfile $profile): void
    {
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturn($this->queryResult($profile));
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn(new IdentityOutputDto(10, 'Identity', 'identity@example.invalid'));
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        (new GetProfileUseCase(new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $this->createStub(InstitutionAssignmentRepository::class), $this->createStub(GroupAssignmentRepository::class)), new PermissionPolicy(), $this->noAvatars(), new AvatarOutputMapper()))->execute(10);
    }

    public static function deniedProfiles(): iterable
    {
        yield 'public registration only' => [null];
        yield 'blocked staff' => [new StaffProfile(10, RoleEnum::ORGANIZER, false, 1, 1)];
        yield 'unknown role' => [new StaffProfile(10, null, true, 1, 1)];
    }

    public function testAuthBlockTakesPrecedenceOverStaff(): void
    {
        $profiles = $this->createMock(StaffProfileRepository::class);
        $profiles->expects(self::never())->method('findByUserId');
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn(null);
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);
        (new GetProfileUseCase(new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $this->createStub(InstitutionAssignmentRepository::class), $this->createStub(GroupAssignmentRepository::class)), new PermissionPolicy(), $this->noAvatars(), new AvatarOutputMapper()))->execute(10);
    }

    private function noAvatars(): StaffAvatarRepositoryInterface
    {
        $avatars = $this->createStub(StaffAvatarRepositoryInterface::class);
        $avatars->method('find')->willReturn(null);

        return $avatars;
    }

    private function queryResult(?StaffProfile $profile): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(null === $profile ? false : [
            'UF_USER_ID' => (string)$profile->userId,
            'UF_ROLE' => $profile->role?->value ?? 'unknown',
            'UF_ACTIVE' => $profile->active ? '1' : '0',
            'UF_REVISION' => (string)$profile->revision,
            'UF_ACCESS_REVISION' => (string)$profile->accessRevision,
        ]);

        return $result;
    }
}
