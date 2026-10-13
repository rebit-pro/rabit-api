<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Structure\Dto\ShootListInputDto;
use Morefoto\Organization\Application\Structure\UseCase\ListAllShootsUseCase;
use Morefoto\Organization\Domain\Structure\Enum\ShootSortEnum;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Presentation\Shoot\Dto\ShootListRequestDto;
use Morefoto\Organization\Presentation\Shoot\Result\ShootListResultMapper;
use Morefoto\Organization\Presentation\Shoot\ShootListInputMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/**
 * UI-T09: the cabinet-wide shoot list (UI-DEC-03, BE-DEC-01).
 *
 * @internal
 */
final class ShootListTest extends TestCase
{
    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/ShootListController.php',
            [
                'Morefoto\Organization\Presentation\Controller',
                'Morefoto\Organization\Presentation\Shoot\ShootListInputMapper',
                'Morefoto\Organization\Presentation\Shoot\Result\ShootListResultMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            ],
            [
                ['Morefoto\Organization\Application\Structure\UseCase\\', 'UseCase'],
                ['Morefoto\Organization\Presentation\Shoot\Dto\\', 'RequestDto'],
            ],
        ));
    }

    public function testNewestShootsComeFirstByDefaultAndOtherColumnsStartAscending(): void
    {
        $mapper = new ShootListInputMapper();

        $default = $mapper->list(new ShootListRequestDto('Bearer token-value', '  Осенн  '));
        self::assertSame(['Осенн', 1, 25, ShootSortEnum::DATE, true], [$default->query, $default->page, $default->pageSize, $default->sort, $default->descending]);

        $byName = $mapper->list(new ShootListRequestDto('Bearer token-value', null, 2, 50, 'name'));
        self::assertSame([ShootSortEnum::NAME, false, 2, 50], [$byName->sort, $byName->descending, $byName->page, $byName->pageSize]);

        self::assertTrue($mapper->list(new ShootListRequestDto('Bearer t', null, 1, 25, 'open', 'desc'))->descending);
        self::assertFalse($mapper->list(new ShootListRequestDto('Bearer t', null, 1, 25, 'date', 'asc'))->descending);
        self::assertSame('token-value', $mapper->bearer(new ShootListRequestDto('Bearer token-value')));
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidQueryIsRejected(ShootListRequestDto $request, string $code): void
    {
        try {
            (new ShootListInputMapper())->list($request);
            self::fail('The request must be rejected.');
        } catch (HttpException $error) {
            self::assertSame([$code, 422], [$error->getMessage(), $error->getCode()]);
        }
    }

    /** @return iterable<string, array{ShootListRequestDto, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'unknown sort' => [new ShootListRequestDto('Bearer t', null, 1, 25, 'revision'), 'INVALID_SORT'];
        yield 'unknown direction' => [new ShootListRequestDto('Bearer t', null, 1, 25, 'name', 'up'), 'INVALID_SORT'];
        yield 'page zero' => [new ShootListRequestDto('Bearer t', null, 0), 'INVALID_PAGE'];
        yield 'page too large' => [new ShootListRequestDto('Bearer t', null, 1, 101), 'INVALID_PAGE'];
        yield 'search too long' => [new ShootListRequestDto('Bearer t', str_repeat('я', 101)), 'INVALID_QUERY'];
    }

    public function testOrganizerSeesEveryInstitutionAndRowsAreMapped(): void
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->expects(self::once())->method('shootList')
            ->with(null, 'Осен', ShootSortEnum::INSTITUTION, false, 25, 25, '2026-10-13 09:00:00')
            ->willReturn($this->rows([
                ['ID' => 4, 'UF_PUBLIC_ID' => 'shoot-4', 'UF_NAME' => 'Осенняя съёмка', 'UF_DATE' => '2026-10-13', 'UF_REVISION' => '3',
                    'INSTITUTION_PUBLIC_ID' => 'inst-1', 'INSTITUTION_NAME' => 'Детский сад «Солнышко»', 'GROUP_COUNT' => '9', 'OPEN_COUNT' => '4', 'TOTAL' => '27'],
                ['ID' => 9, 'UF_PUBLIC_ID' => 'shoot-9', 'UF_NAME' => 'Осень без даты', 'UF_DATE' => null, 'UF_REVISION' => '1',
                    'INSTITUTION_PUBLIC_ID' => 'inst-2', 'INSTITUTION_NAME' => 'Школа №29', 'GROUP_COUNT' => '0', 'OPEN_COUNT' => '0', 'TOTAL' => '27'],
            ]))
        ;

        $output = $this->useCase($structure, $this->scope('organizer', []))->execute(7, 'token', new ShootListInputDto('Осен', 2, 25, ShootSortEnum::INSTITUTION, false));
        $mapper = new ShootListResultMapper();

        self::assertSame(['page' => 2, 'pageSize' => 25, 'total' => 27, 'totalPages' => 2], $mapper->meta($output));
        $first = $mapper->list($output)->items[0];
        self::assertSame(
            ['shoot-4', 'inst-1', 'Детский сад «Солнышко»', 'Осенняя съёмка', '2026-10-13', 3, 9, 4],
            [$first->id, $first->institutionId, $first->institutionName, $first->name, $first->date, $first->revision, $first->groupCount, $first->openGroupCount],
        );
        self::assertNull($mapper->list($output)->items[1]->date);
    }

    public function testCuratorSeesOnlyAssignedInstitutionsAndAnEmptyPageKeepsTheTotal(): void
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->expects(self::once())->method('shootList')
            ->with([3, 5], '', ShootSortEnum::DATE, true, 25, 0, self::anything())
            ->willReturn($this->rows([['ID' => null, 'TOTAL' => '0']]))
        ;

        $output = $this->useCase($structure, $this->scope('curator', [3, 5]))->execute(7, 'token', new ShootListInputDto('', 1, 25, ShootSortEnum::DATE, true));

        self::assertSame([[], 0], [$output->items, $output->total]);
    }

    public function testTeacherHasNoShootList(): void
    {
        $structure = $this->createMock(StructureRepository::class);
        $structure->expects(self::never())->method('shootList');

        $this->expectExceptionObject(new HttpException('FORBIDDEN', 403));
        $this->useCase($structure, $this->scope('teacher', [3]))->execute(7, 'token', new ShootListInputDto('', 1, 25, ShootSortEnum::DATE, true));
    }

    public function testAccessChangedDuringTheReadGivesNoPage(): void
    {
        $structure = $this->createStub(StructureRepository::class);
        $structure->method('shootList')->willReturn($this->rows([['ID' => null, 'TOTAL' => '0']]));
        $access = $this->createStub(InstitutionAccessInterface::class);
        $access->method('scope')->willReturnOnConsecutiveCalls(
            new InstitutionScopeOutputDto('curator', 4, [3]),
            new InstitutionScopeOutputDto('curator', 5, [3]),
        );
        $access->method('signature')->willReturn('signature');

        $this->expectExceptionObject(new HttpException('ACCESS_CHANGED', 409));
        $this->useCase($structure, $access)->execute(7, 'token', new ShootListInputDto('', 1, 25, ShootSortEnum::DATE, true));
    }

    /** @param list<int> $institutionIds */
    private function scope(string $role, array $institutionIds): InstitutionAccessInterface
    {
        $access = $this->createStub(InstitutionAccessInterface::class);
        $access->method('scope')->willReturn(new InstitutionScopeOutputDto($role, 4, $institutionIds));
        $access->method('signature')->willReturn('signature');

        return $access;
    }

    private function useCase(StructureRepository $structure, InstitutionAccessInterface $access): ListAllShootsUseCase
    {
        $tokens = $this->createStub(TokenResolverInterface::class);
        $tokens->method('resolveUserId')->willReturn(7);
        $clock = $this->createStub(CalendarClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('Europe/Moscow')));

        return new ListAllShootsUseCase($structure, $access, $tokens, $clock);
    }

    /** @param list<array<string, mixed>> $rows */
    private function rows(array $rows): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);

        return $result;
    }
}
