<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Bitrix\Main\DB\Result as DbResult;
use Bitrix\Main\ORM\Query\Result;
use Morefoto\Access\Application\Assignment\Service\InstitutionAccess;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Access\Domain\Staff\Repository\CatalogAccessRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Morefoto\Access\Infrastructure\Adapter\AccessGuard;
use Morefoto\Access\Infrastructure\Adapter\CatalogAccessGuard;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bitrix-result.php';

/**
 * Отказы Access несут код контракта ошибок в сообщении исключения при прежнем HTTP-статусе.
 *
 * @internal
 */
final class AccessRefusalCodesTest extends TestCase
{
    private const int ACTOR = 21;

    public function testInactiveIdentityIsUnauthorized(): void
    {
        $this->assertRefusal('UNAUTHORIZED', 401, fn(): mixed => $this->authorization('teacher', identity: false)->context(self::ACTOR));
    }

    public function testMissingOrDisabledProfileIsForbidden(): void
    {
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->authorization(null)->context(self::ACTOR));
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->authorization('teacher', active: false)->context(self::ACTOR));
    }

    public function testActionOutsideRoleIsForbidden(): void
    {
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->authorization('teacher')->assertCan(self::ACTOR, PermissionEnum::STAFF_MANAGE));
    }

    public function testForeignScopedResourceIsHiddenAsNotFound(): void
    {
        $this->assertRefusal('NOT_FOUND', 404, fn(): mixed => $this->authorization('teacher')->assertCan(self::ACTOR, PermissionEnum::GROUP_READ, 10, 101));
    }

    public function testUnknownGuardActionIsForbidden(): void
    {
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => (new AccessGuard($this->authorization('organizer')))->assertCan(self::ACTOR, 'unknown.action'));
    }

    public function testInstitutionScopeOfTeacherIsForbidden(): void
    {
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->institutionAccess('teacher', self::ACTOR)->scope(self::ACTOR));
    }

    public function testParticipantsLockRejectsForeignBearerAndNonOrganizer(): void
    {
        $this->assertRefusal('UNAUTHORIZED', 401, fn(): mixed => $this->institutionAccess('organizer', 99)->lockParticipants(self::ACTOR, 'bearer', []));
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->institutionAccess('curator', self::ACTOR)->lockParticipants(self::ACTOR, 'bearer', []));
    }

    public function testCatalogGuardRefusalsCarryCodes(): void
    {
        $this->assertRefusal('UNAUTHORIZED', 401, fn(): mixed => $this->catalogGuard('organizer', self::ACTOR)->lockOrganizer(self::ACTOR, ''));
        $this->assertRefusal('FORBIDDEN', 403, fn(): mixed => $this->catalogGuard('curator', self::ACTOR)->lockOrganizer(self::ACTOR, 'bearer'));
        $this->assertRefusal('UNAUTHORIZED', 401, fn(): mixed => $this->catalogGuard('organizer', new HttpException('TOKEN_EXPIRED', 401))->lockOrganizer(self::ACTOR, 'bearer'));
        $this->assertRefusal('ACCESS_UNAVAILABLE', 503, fn(): mixed => $this->catalogGuard('organizer', new \RuntimeException('DB is down'))->lockOrganizer(self::ACTOR, 'bearer'));
    }

    /** @param callable(): mixed $operation */
    private function assertRefusal(string $code, int $status, callable $operation): void
    {
        try {
            $operation();
            self::fail($code . ' expected.');
        } catch (CatalogAccessException|HttpException $error) {
            self::assertSame([$code, $status], [$error->getMessage(), $error->getCode()]);
        }
    }

    /**
     * @return array{
     *     UF_USER_ID: int,
     *     UF_ROLE: string,
     *     UF_ACTIVE: int,
     *     UF_REVISION: int,
     *     UF_ACCESS_REVISION: int,
     * }|false
     */
    private function row(?string $role, bool $active = true): array|false
    {
        return null === $role ? false : ['UF_USER_ID' => self::ACTOR, 'UF_ROLE' => $role, 'UF_ACTIVE' => $active ? 1 : 0, 'UF_REVISION' => 4, 'UF_ACCESS_REVISION' => 3];
    }

    private function authorization(?string $role, bool $active = true, bool $identity = true): StaffAuthorization
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($this->row($role, $active));
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturn($result);
        $groups = $this->createStub(GroupAssignmentRepository::class);
        $groups->method('groupIds')->willReturn([100]);

        return new StaffAuthorization($profiles, $this->identities($identity), new PermissionPolicy(), $this->createStub(InstitutionAssignmentRepository::class), $groups);
    }

    private function institutionAccess(string $role, int $bearerOwner): InstitutionAccess
    {
        $tokens = $this->createStub(TokenResolverInterface::class);
        $tokens->method('resolveUserId')->willReturn($bearerOwner);

        return new InstitutionAccess(
            $this->createStub(InstitutionAssignmentRepository::class),
            $this->createStub(StaffProfileRepository::class),
            $this->authorization($role),
            $this->identities(true),
            $tokens,
        );
    }

    private function catalogGuard(string $role, int|\Throwable $bearer): CatalogAccessGuard
    {
        $result = $this->createStub(DbResult::class);
        $result->method('fetch')->willReturn($this->row($role));
        $profiles = $this->createStub(CatalogAccessRepository::class);
        $profiles->method('lockProfile')->willReturn($result);
        $tokens = $this->createStub(TokenResolverInterface::class);
        $bearer instanceof \Throwable ? $tokens->method('resolveUserId')->willThrowException($bearer) : $tokens->method('resolveUserId')->willReturn($bearer);

        return new CatalogAccessGuard($profiles, $this->identities(true), $tokens, new PermissionPolicy());
    }

    private function identities(bool $active): IdentityGatewayInterface
    {
        $identity = $active ? new IdentityOutputDto(self::ACTOR, 'Сотрудник', 'staff@example.invalid') : null;
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn($identity);
        $identities->method('lockActive')->willReturn($identity);

        return $identities;
    }
}
