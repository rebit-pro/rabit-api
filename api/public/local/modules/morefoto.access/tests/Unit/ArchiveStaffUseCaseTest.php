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
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class ArchiveStaffUseCaseTest extends TestCase
{
    private const int ACTOR = 1;
    private const int TARGET = 7;
    private const string BEARER = 'actor-session';

    /** @var list<string> */
    private array $calls = [];
    private bool $locked = false;
    /** What another organizer did to the actor while this request waited for the access lock. */
    private bool $actorDisabledWhileWaiting = false;
    private ?HttpException $lockRefusal = null;

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

        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);

        self::assertSame(['lock', 'institutions', 'groups', 'journal', 'profile', 'state', 'identity'], $this->calls);
    }

    public function testDisabledOrganizerIsRemovedWithoutTheLastOrganizerCheck(): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 0, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $staff->expects(self::never())->method('activeOrganizerCountForUpdate');
        $staff->expects(self::once())->method('deleteProfile');

        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
    }

    public function testLastActiveOrganizerStays(): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1, 'AUTH_ACTIVE' => 'Y', 'AUTH_PENDING' => 0]);
        $staff->method('activeOrganizerCountForUpdate')->willReturn(1);
        $staff->expects(self::never())->method('deleteProfile');

        $this->expectRefusal('LAST_ORGANIZER', 409);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
    }

    /** Review #96 P2: only a target inside the counted set of active organizers is protected. */
    #[DataProvider('organizersOutsideTheActiveSet')]
    public function testInvitedOrBlockedOrganizerIsRemovedNextToTheOnlyActiveOne(string $authActive, int $authPending): void
    {
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1, 'AUTH_ACTIVE' => $authActive, 'AUTH_PENDING' => $authPending]);
        $staff->method('activeOrganizerCountForUpdate')->willReturn(1);
        $staff->expects(self::once())->method('deleteProfile')->with(self::TARGET);

        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
    }

    /** @return iterable<string, array{string, int}> */
    public static function organizersOutsideTheActiveSet(): iterable
    {
        yield 'invited' => ['N', 1];
        yield 'blocked identity' => ['N', 0];
    }

    /** Review #96 P1: the actor is checked again under the access lock, before any change. */
    public function testActorDisabledWhileWaitingForTheLockChangesNothing(): void
    {
        $this->actorDisabledWhileWaiting = true;
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'curator', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $staff->expects(self::never())->method('recordChange');
        $staff->expects(self::never())->method('deleteProfile');

        try {
            $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
            self::fail('Expected FORBIDDEN');
        } catch (HttpException $error) {
            self::assertSame(['FORBIDDEN', 403], [$error->getMessage(), $error->getCode()]);
        }
        self::assertSame(['lock'], $this->calls);
    }

    public function testSessionRevokedWhileWaitingForTheLockChangesNothing(): void
    {
        $this->lockRefusal = new HttpException('UNAUTHORIZED', 401);
        $staff = $this->staff(['UF_USER_ID' => self::TARGET, 'UF_ROLE' => 'curator', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $staff->expects(self::never())->method('find');
        $staff->expects(self::never())->method('deleteProfile');

        $this->expectRefusal('UNAUTHORIZED', 401);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
    }

    public function testActorCannotRemoveThemselves(): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('find');

        $this->expectRefusal('CANNOT_ARCHIVE_SELF', 409);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::ACTOR);
    }

    public function testMissingStaffIsNotFound(): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('deleteProfile');

        $this->expectRefusal('STAFF_NOT_FOUND', 404);
        $this->useCase($staff, 'organizer')->execute(self::ACTOR, self::BEARER, self::TARGET);
    }

    #[DataProvider('rolesWithoutStaffManagement')]
    public function testOnlyOrganizerRemovesStaff(string $role): void
    {
        $staff = $this->staff(false);
        $staff->expects(self::never())->method('find');

        $this->expectRefusal('FORBIDDEN', 403);
        $this->useCase($staff, $role)->execute(self::ACTOR, self::BEARER, self::TARGET);
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
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturnCallback(function() use ($actorRole): QueryResult {
            $profile = $this->createStub(QueryResult::class);
            $active = $this->locked && $this->actorDisabledWhileWaiting ? 0 : 1;
            $profile->method('fetch')->willReturn(['UF_USER_ID' => self::ACTOR, 'UF_ROLE' => $actorRole, 'UF_ACTIVE' => $active, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);

            return $profile;
        });
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
        $access = $this->createMock(InstitutionAccessInterface::class);
        $access->method('lockParticipants')->with(self::ACTOR, self::BEARER, [self::TARGET])->willReturnCallback(function(): void {
            $this->calls[] = 'lock';
            $this->locked = true;
            if (null !== $this->lockRefusal) {
                throw $this->lockRefusal;
            }
        });

        return new ArchiveStaffUseCase(
            new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $institutions, $groups),
            $state,
            $staff,
            $institutions,
            $groups,
            $staffIdentities,
            $access,
        );
    }

    private function expectRefusal(string $code, int $status): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode($status);
    }
}
