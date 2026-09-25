<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Query\Result as QueryResult;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Staff\UseCase\ArchiveStaffUseCase;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Entity\StaffProfile;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class ArchiveStaffUseCaseTest extends TestCase
{
    private const int ACTOR = 1;
    private const int TARGET = 7;

    /** @var list<string> */
    private array $calls = [];

    public function testArchiveRemovesAccessButKeepsHistory(): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'curator', 'UF_ACTIVE' => 1, 'UF_REVISION' => 4, 'UF_ACCESS_REVISION' => 3]);
        $staff->expects(self::once())->method('recordChange')
            ->with(self::TARGET, 4, 5, self::ACTOR, self::matchesRegularExpression('/^[0-9a-f-]{36}$/D'), '{"archived":true}')
            ->willReturnCallback(function(): void { $this->calls[] = 'journal'; })
        ;
        $staff->expects(self::once())->method('deleteProfile')->with(self::TARGET)
            ->willReturnCallback(function(): void { $this->calls[] = 'profile'; })
        ;
        $staff->expects(self::never())->method('activeOrganizerCountForUpdate');

        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::TARGET);

        self::assertSame(['institutions', 'groups', 'journal', 'profile', 'state', 'identity'], $this->calls);
    }

    public function testDisabledOrganizerIsRemovedWithoutTheLastOrganizerCheck(): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 0, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $staff->expects(self::never())->method('activeOrganizerCountForUpdate');
        $staff->expects(self::once())->method('deleteProfile');

        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::TARGET);
    }

    public function testLastActiveOrganizerStays(): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $staff->method('activeOrganizerCountForUpdate')->willReturn(1);
        $staff->expects(self::never())->method('deleteProfile');

        $this->expectRefusal('LAST_ORGANIZER', 409);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::TARGET);
    }

    public function testActorCannotRemoveThemselves(): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('find');

        $this->expectRefusal('CANNOT_ARCHIVE_SELF', 409);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::ACTOR);
    }

    public function testMissingStaffIsNotFound(): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('deleteProfile');

        $this->expectRefusal('STAFF_NOT_FOUND', 404);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::TARGET);
    }

    #[DataProvider('rolesWithoutStaffManagement')]
    public function testOnlyOrganizerRemovesStaff(string $role): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('find');

        $this->expectRefusal('FORBIDDEN', 403);
        $this->useCase($staff, $role)->execute(self::ACTOR, self::TARGET);
    }

    /** @return iterable<string, array{string}> */
    public static function rolesWithoutStaffManagement(): iterable
    {
        yield 'curator' => ['curator'];
        yield 'head' => ['head'];
        yield 'teacher' => ['teacher'];
    }

    /** @param array<string, int|string>|false $row */
    private function staff(array|false $row): MockObject&StaffManagementRepository
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($row);
        $staff = $this->createMock(StaffManagementRepository::class);
        $staff->method('find')->willReturn($result);
        $staff->method('profile')->willReturnCallback(
            static fn(array $row): StaffProfile => StaffProfile::fromRow($row)
                ?? throw new \LogicException('Profile row expected.'),
        );

        return $staff;
    }

    private function useCase(StaffManagementRepository $staff, string $actorRole): ArchiveStaffUseCase
    {
        $profile = $this->createStub(QueryResult::class);
        $profile->method('fetch')->willReturn(['UF_USER_ID' => self::ACTOR, 'UF_ROLE' => $actorRole, 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturn($profile);
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn(new IdentityOutputDto(self::ACTOR, 'Organizer', 'organizer@example.invalid'));
        $institutions = $this->createStub(InstitutionAssignmentRepository::class);
        $institutions->method('deleteForUser')->willReturnCallback(function(): void { $this->calls[] = 'institutions'; });
        $institutions->method('advanceState')->willReturnCallback(function(): void { $this->calls[] = 'state'; });
        $groups = $this->createStub(GroupAssignmentRepository::class);
        $groups->method('deleteForUser')->willReturnCallback(function(): void { $this->calls[] = 'groups'; });
        $state = $this->createStub(AccessStateRepository::class);
        $state->method('run')->willReturnCallback(static fn(callable $operation): mixed => $operation());
        $staffIdentities = $this->createStub(StaffIdentityGatewayInterface::class);
        $staffIdentities->method('archive')->willReturnCallback(function(): void { $this->calls[] = 'identity'; });

        return new ArchiveStaffUseCase(
            new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $institutions, $groups),
            $state,
            $staff,
            $institutions,
            $groups,
            $staffIdentities,
        );
    }

    private function expectRefusal(string $code, int $status): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode($status);
    }
}
