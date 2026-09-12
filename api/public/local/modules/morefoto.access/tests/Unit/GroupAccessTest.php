<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Query\Result as OrmResult;
use Morefoto\Access\Application\Assignment\Service\GroupAccess;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bitrix-result.php';

/** @internal */
final class GroupAccessTest extends TestCase
{
    private GroupAssignmentRepository&MockObject $groups;
    private InstitutionAccessInterface&Stub $access;
    private InstitutionAssignmentRepository&MockObject $changes;
    private MockObject&StaffProfileRepository $profiles;
    private IdentityGatewayInterface&MockObject $identities;

    public function testReplacementRecordsReasonAndRevokesBothSessionsInUserIdOrder(): void
    {
        $service = $this->service(22);
        $this->access->method('signature')->willReturn('a7', 'a8');
        $this->activeProfiles();
        $this->groups->expects(self::once())->method('set')->with(100, 21);
        $changed = [];
        $this->changes->expects(self::exactly(2))->method('advanceUser')->willReturnCallback(
            static function(int $userId, int $revision, int $actor, string $operationId, string $delta) use (&$changed): void {
                self::assertSame(4, $revision);
                self::assertSame(1, $actor);
                self::assertSame('operation', $operationId);
                self::assertSame([
                    'groupId' => 100, 'role' => 'teacher', 'fromUserId' => 22,
                    'toUserId' => 21, 'reason' => 'Teacher replacement',
                ], json_decode($delta, true, 512, JSON_THROW_ON_ERROR));
                $changed[] = $userId;
            },
        );
        $revoked = [];
        $this->identities->expects(self::exactly(2))->method('revokeSessions')->willReturnCallback(static function(int $id) use (&$revoked): void { $revoked[] = $id; });
        $this->changes->expects(self::once())->method('advanceState');

        self::assertSame('a8', $service->replace(100, new GroupAssignmentOutputDto(21), 'a7', true, 1, 'operation', ' Teacher replacement '));
        self::assertSame([21, 22], $changed);
        self::assertSame([21, 22], $revoked);
    }

    public function testInitialAssignmentRequiresSignatureButNoReplacementReason(): void
    {
        $service = $this->service(null);
        $this->access->method('signature')->willReturn('a7', 'a8');
        $this->activeProfiles();
        $this->groups->expects(self::once())->method('set')->with(100, 21);
        $this->changes->expects(self::once())->method('advanceUser');
        $this->identities->expects(self::once())->method('revokeSessions')->with(21);
        $this->changes->expects(self::once())->method('advanceState');
        self::assertSame('a8', $service->replace(100, new GroupAssignmentOutputDto(21), 'a7', false, 1, 'operation', null));
    }

    public function testRemovalRevokesFormerTeacherAndKeepsReason(): void
    {
        $service = $this->service(21);
        $this->access->method('signature')->willReturn('a7', 'a8');
        $this->activeProfiles();
        $this->identities->expects(self::never())->method('findActive');
        $this->groups->expects(self::once())->method('set')->with(100, null);
        $this->changes->expects(self::once())->method('advanceUser')->with(21, 4, 1, 'operation', self::callback(static fn(string $json): bool => ['groupId' => 100, 'role' => 'teacher', 'fromUserId' => 21, 'toUserId' => null, 'reason' => 'No longer assigned'] === json_decode($json, true)));
        $this->identities->expects(self::once())->method('revokeSessions')->with(21);
        $this->changes->expects(self::once())->method('advanceState');
        self::assertSame('a8', $service->replace(100, new GroupAssignmentOutputDto(), 'a7', true, 1, 'operation', 'No longer assigned'));
    }

    public function testUnchangedAssignmentDoesNotInvalidateSessionsAgain(): void
    {
        $service = $this->service(21);
        $this->access->method('signature')->willReturn('a9');
        $this->noChanges();
        $this->profiles->expects(self::never())->method('findByUserId');
        self::assertSame('a9', $service->replace(100, new GroupAssignmentOutputDto(21), 'old', false, 1, 'operation', null));
    }

    #[DataProvider('invalidReplacements')]
    public function testInvalidReplacementCannotChangeAssignments(?int $old, ?string $signature, bool $replace, ?string $reason, int $status, string $code): void
    {
        $service = $this->service($old);
        $this->access->method('signature')->willReturn('a7');
        $this->noChanges();
        $this->profiles->expects(self::never())->method('findByUserId');
        $this->expectException(HttpException::class);
        $this->expectExceptionCode($status);
        $this->expectExceptionMessage($code);
        $service->replace(100, new GroupAssignmentOutputDto(22), $signature, $replace, 1, 'operation', $reason);
    }

    public static function invalidReplacements(): iterable
    {
        yield 'missing initial signature' => [null, null, false, null, 409, 'ASSIGNMENTS_CHANGED'];
        yield 'stale signature cannot be bypassed' => [21, 'a6', true, 'Confirmed', 409, 'ASSIGNMENTS_CHANGED'];
        yield 'occupied slot requires confirmation' => [21, 'a7', false, 'Confirmed', 409, 'ASSIGNMENT_OCCUPIED'];
        yield 'missing reason' => [21, 'a7', true, null, 422, 'ASSIGNMENT_REASON_REQUIRED'];
        yield 'blank reason' => [21, 'a7', true, '   ', 422, 'ASSIGNMENT_REASON_REQUIRED'];
        yield 'long reason' => [21, 'a7', true, str_repeat('x', 2001), 422, 'INVALID_ASSIGNMENT_REASON'];
    }

    #[DataProvider('invalidAssignees')]
    public function testAssigneeMustHaveActiveTeacherProfile(?string $role, bool $active, bool $identity): void
    {
        $service = $this->service(null);
        $this->access->method('signature')->willReturn('a7');
        $this->noChanges();
        $result = $this->createStub(OrmResult::class);
        $result->method('fetch')->willReturn(null === $role ? false : ['UF_USER_ID' => 21, 'UF_ROLE' => $role, 'UF_ACTIVE' => $active ? 1 : 0, 'UF_REVISION' => 4, 'UF_ACCESS_REVISION' => 3]);
        $this->profiles->expects(self::once())->method('findByUserId')->willReturn($result);
        $this->identities->method('findActive')->willReturn($identity ? new IdentityOutputDto(21, 'Teacher', 'teacher@example.invalid') : null);
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('INVALID_ASSIGNEE');
        $service->replace(100, new GroupAssignmentOutputDto(21), 'a7', false, 1, 'operation', null);
    }

    public static function invalidAssignees(): iterable
    {
        yield 'missing profile' => [null, true, true];
        yield 'curator cannot be teacher' => ['curator', true, true];
        yield 'inactive teacher' => ['teacher', false, true];
        yield 'inactive Auth identity' => ['teacher', true, false];
    }

    public function testRevisionExhaustionCannotPartiallyChangeAssignment(): void
    {
        $service = $this->service(21);
        $this->access->method('signature')->willReturn('a7');
        $this->noChanges();
        $result = $this->createStub(OrmResult::class);
        $result->method('fetch')->willReturn(['UF_USER_ID' => 21, 'UF_ROLE' => 'teacher', 'UF_ACTIVE' => 1, 'UF_REVISION' => 2147483647, 'UF_ACCESS_REVISION' => 3]);
        $this->profiles->expects(self::once())->method('findByUserId')->willReturn($result);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('STAFF_VERSION_UNAVAILABLE');
        $service->replace(100, new GroupAssignmentOutputDto(), 'a7', true, 1, 'operation', 'End of assignment');
    }

    private function service(?int $old): GroupAccess
    {
        $this->groups = $this->createMock(GroupAssignmentRepository::class);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(null === $old ? false : ['UF_GROUP_ID' => 100, 'UF_USER_ID' => $old], false);
        $this->groups->method('assignments')->willReturn($result);
        $this->access = $this->createStub(InstitutionAccessInterface::class);
        $this->changes = $this->createMock(InstitutionAssignmentRepository::class);
        $this->profiles = $this->createMock(StaffProfileRepository::class);
        $this->identities = $this->createMock(IdentityGatewayInterface::class);

        return new GroupAccess($this->groups, $this->access, $this->changes, $this->profiles, $this->identities);
    }

    private function activeProfiles(): void
    {
        $this->profiles->expects(self::atLeastOnce())->method('findByUserId')->willReturnCallback(function(int $id): OrmResult {
            $result = $this->createStub(OrmResult::class);
            $result->method('fetch')->willReturn(['UF_USER_ID' => $id, 'UF_ROLE' => 'teacher', 'UF_ACTIVE' => 1, 'UF_REVISION' => 4, 'UF_ACCESS_REVISION' => 3]);

            return $result;
        });
        $this->identities->method('findActive')->willReturnCallback(static fn(int $id): IdentityOutputDto => new IdentityOutputDto($id, 'Teacher', 'teacher@example.invalid'));
    }

    private function noChanges(): void
    {
        $this->groups->expects(self::never())->method('set');
        $this->changes->expects(self::never())->method('advanceUser');
        $this->changes->expects(self::never())->method('advanceState');
        $this->identities->expects(self::never())->method('revokeSessions');
    }
}
