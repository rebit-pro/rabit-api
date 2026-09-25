<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Query\Result as QueryResult;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Morefoto\Access\Application\Staff\Contract\AssignmentDirectoryInterface;
use Morefoto\Access\Application\Staff\Dto\ListStaffInputDto;
use Morefoto\Access\Application\Staff\UseCase\StaffDirectoryUseCase;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Enum\AccountStatusEnum;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Morefoto\Access\Domain\Staff\Enum\StaffSortEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffManagementRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use Morefoto\Access\Domain\Staff\Service\StaffCountFacets;
use Morefoto\Access\Presentation\Staff\Dto\StaffListRequestDto;
use Morefoto\Access\Presentation\Staff\Result\StaffListResultMapper;
use Morefoto\Access\Presentation\Staff\StaffListInputMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class StaffListSummaryTest extends TestCase
{
    /** @var list<array{role: string, accountStatus: string, total: int}> */
    private const array COUNTS = [
        ['role' => 'teacher', 'accountStatus' => 'pending', 'total' => 3],
        ['role' => 'teacher', 'accountStatus' => 'active', 'total' => 5],
        ['role' => 'curator', 'accountStatus' => 'active', 'total' => 2],
        ['role' => 'curator', 'accountStatus' => 'blocked', 'total' => 1],
        ['role' => 'organizer', 'accountStatus' => 'active', 'total' => 1],
    ];

    public function testEachTileSplitKeepsTheOtherFilter(): void
    {
        $facets = new StaffCountFacets();

        self::assertSame([
            'byAccountStatus' => ['pending' => 3, 'active' => 8, 'blocked' => 1],
            'byRole' => ['organizer' => 1, 'curator' => 3, 'head' => 0, 'teacher' => 8],
        ], $facets->split(self::COUNTS, null, null));
        // Filtered by teachers: statuses count only teachers, roles still show every role.
        self::assertSame(['pending' => 3, 'active' => 5, 'blocked' => 0], $facets->split(self::COUNTS, RoleEnum::TEACHER, null)['byAccountStatus']);
        self::assertSame(['organizer' => 1, 'curator' => 2, 'head' => 0, 'teacher' => 5], $facets->split(self::COUNTS, RoleEnum::TEACHER, AccountStatusEnum::ACTIVE)['byRole']);
    }

    public function testDirectoryCountsTheSameFiltersAsItsPage(): void
    {
        $input = new ListStaffInputDto('анна', RoleEnum::TEACHER, true, AccountStatusEnum::PENDING, 1, 25);
        $staff = $this->createMock(StaffManagementRepository::class);
        $staff->method('list')->with($input)->willReturn([new Result(), 0]);
        $staff->expects(self::once())->method('counts')->with($input)->willReturn(self::COUNTS);

        $page = $this->directory($staff)->list(1, $input);
        $meta = (new StaffListResultMapper())->meta($page);

        self::assertSame(['byAccountStatus' => ['pending' => 3, 'active' => 5, 'blocked' => 0], 'byRole' => ['organizer' => 0, 'curator' => 0, 'head' => 0, 'teacher' => 3]], $meta['summary']);
        self::assertSame([1, 25, 0, 0], [$meta['page'], $meta['pageSize'], $meta['total'], $meta['totalPages']]);
    }

    public function testRequestFiltersKeepTheirErrorCodes(): void
    {
        $input = (new StaffListInputMapper())->list(new StaffListRequestDto(' Анна ', 'curator', 'false', 'blocked', '2', '50'));

        self::assertSame(['Анна', RoleEnum::CURATOR, false, AccountStatusEnum::BLOCKED, 2, 50], [$input->query, $input->role, $input->active, $input->accountStatus, $input->page, $input->pageSize]);
    }

    public function testSortDefaultsToNameAndReadsTheDirection(): void
    {
        $mapper = new StaffListInputMapper();
        $default = $mapper->list(new StaffListRequestDto());
        $explicit = $mapper->list(new StaffListRequestDto(sort: 'status', direction: 'desc'));

        self::assertSame(
            [StaffSortEnum::NAME, false, StaffSortEnum::STATUS, true],
            [$default->sort, $default->descending, $explicit->sort, $explicit->descending],
        );
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidFilterIsRejected(StaffListRequestDto $request, string $code): void
    {
        try {
            (new StaffListInputMapper())->list($request);
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame([$code, 422], [$error->getMessage(), $error->getCode()]);
        }
    }

    /** @return iterable<string, array{StaffListRequestDto, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'role' => [new StaffListRequestDto(role: 'admin'), 'INVALID_ROLE'];
        yield 'account status' => [new StaffListRequestDto(accountStatus: 'deleted'), 'INVALID_ACCOUNT_STATUS'];
        yield 'active' => [new StaffListRequestDto(active: 'maybe'), 'INVALID_ACTIVE'];
        yield 'page' => [new StaffListRequestDto(page: '0'), 'INVALID_PAGE'];
        yield 'page size' => [new StaffListRequestDto(pageSize: '1e3'), 'INVALID_PAGE'];
        yield 'sort' => [new StaffListRequestDto(sort: 'password'), 'INVALID_SORT'];
        yield 'direction' => [new StaffListRequestDto(direction: 'up'), 'INVALID_SORT'];
    }

    private function directory(StaffManagementRepository $staff): StaffDirectoryUseCase
    {
        $profile = $this->createStub(QueryResult::class);
        $profile->method('fetch')->willReturn(['UF_USER_ID' => 1, 'UF_ROLE' => 'organizer', 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]);
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturn($profile);
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturn(new IdentityOutputDto(1, 'Organizer', 'organizer@example.invalid'));
        $institutions = $this->createStub(InstitutionAssignmentRepository::class);
        $groups = $this->createStub(GroupAssignmentRepository::class);

        return new StaffDirectoryUseCase(
            new StaffAuthorization($profiles, $identities, new PermissionPolicy(), $institutions, $groups),
            $staff,
            $this->createStub(AssignmentDirectoryInterface::class),
            $institutions,
            $groups,
            $this->createStub(InstitutionAccessInterface::class),
            $this->createStub(StaffIdentityGatewayInterface::class),
            new AvatarOutputMapper(),
            new StaffCountFacets(),
        );
    }
}
