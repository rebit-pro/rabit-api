<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Sales\Dto\GroupSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Application\Sales\UseCase\GetGroupSalesUseCase;
use Morefoto\Commerce\Domain\Sales\Repository\GroupSalesRepository;
use Morefoto\Commerce\Infrastructure\Sales\SalesAccess;
use Morefoto\Commerce\Presentation\Sales\SalesResultMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E9: COM-14 — the sales of a group for its teacher and the staff who own it.
 *
 * @internal
 */
final class GroupSalesTest extends TestCase
{
    private const string GROUP = '0f8fad5b-d9cb-469f-a165-70867728950e';

    /**
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     */
    #[DataProvider('scopes')]
    public function testOnlyTheOwnScopeSeesTheGroup(string $role, array $institutionIds, array $groupIds, ?string $error): void
    {
        $sales = $this->createStub(GroupSalesRepository::class);
        $sales->method('totals')->willReturn(['ORDERS' => 0, 'FAMILIES' => 0, 'PRINTS' => 0, 'DIGITAL' => 0, 'BUNDLES' => 0, 'PAID_TOTAL' => 0]);
        $sales->method('printsByProduct')->willReturn(new Result());
        $useCase = new GetGroupSalesUseCase(new SalesAccess($this->actor($role, $institutionIds, $groupIds)), $this->directory($this->group()), $sales);

        try {
            $output = $useCase->execute(5, self::GROUP);
            self::assertNull($error);
            self::assertSame(self::GROUP, $output->groupId);
        } catch (HttpException $exception) {
            self::assertSame($error, $exception->getMessage());
            self::assertSame('FORBIDDEN' === $error ? 403 : 404, $exception->getCode());
        }
    }

    /** @return iterable<string, array{string, list<int>, list<int>, null|string}> */
    public static function scopes(): iterable
    {
        yield 'organizer sees any group' => ['organizer', [], [], null];
        yield 'curator of the institution' => ['curator', [3, 7], [], null];
        yield 'curator of another institution' => ['curator', [4], [], 'GROUP_NOT_FOUND'];
        yield 'teacher of the group' => ['teacher', [], [11, 21], null];
        yield 'teacher of another group' => ['teacher', [], [12], 'GROUP_NOT_FOUND'];
        yield 'teacher without groups' => ['teacher', [], [], 'GROUP_NOT_FOUND'];
        yield 'head has no sales' => ['head', [7], [], 'FORBIDDEN'];
    }

    public function testAMissingGroupLooksLikeAForeignOne(): void
    {
        $sales = $this->createMock(GroupSalesRepository::class);
        $sales->expects(self::never())->method('totals');
        $useCase = new GetGroupSalesUseCase(new SalesAccess($this->actor('organizer', [], [])), $this->directory(null), $sales);

        $this->expectExceptionObject(new HttpException('GROUP_NOT_FOUND', 404));
        $useCase->execute(5, self::GROUP);
    }

    public function testTheSummaryKeepsTheRepositoryNumbersAndTheProductOrder(): void
    {
        $sales = $this->createMock(GroupSalesRepository::class);
        $sales->expects(self::once())->method('totals')->with(self::GROUP)
            ->willReturn(['ORDERS' => 4, 'FAMILIES' => 3, 'PRINTS' => 9, 'DIGITAL' => 5, 'BUNDLES' => 1, 'PAID_TOTAL' => 1_234_550])
        ;
        $sales->expects(self::once())->method('printsByProduct')->with(self::GROUP)->willReturn($this->rows([
            ['PRODUCT_PUBLIC_ID' => 'p-10x15', 'NAME' => 'Печать 10×15', 'PRINTS' => '7'],
            ['PRODUCT_PUBLIC_ID' => 'p-magnet', 'NAME' => 'Магнит', 'PRINTS' => '2'],
        ]));
        $useCase = new GetGroupSalesUseCase(new SalesAccess($this->actor('teacher', [], [11])), $this->directory($this->group()), $sales);

        $output = $useCase->execute(5, self::GROUP);

        self::assertEquals(new GroupSalesOutputDto(
            groupId: self::GROUP,
            paidOrders: 4,
            families: 3,
            prints: 9,
            digitalFiles: 5,
            bundles: 1,
            paidTotal: 1_234_550,
            products: [new ProductSalesOutputDto('p-10x15', 'Печать 10×15', 7), new ProductSalesOutputDto('p-magnet', 'Магнит', 2)],
        ), $output);
        $result = (new SalesResultMapper())->group($output);
        self::assertSame(
            ['groupId', 'paidOrders', 'families', 'prints', 'digitalFiles', 'bundles', 'paidTotal', 'products'],
            array_keys(get_object_vars($result)),
        );
        self::assertSame(['id' => 'p-magnet', 'name' => 'Магнит', 'prints' => 2], get_object_vars($result->products[1]));
    }

    public function testAGroupWithoutSalesHasZerosAndNoProducts(): void
    {
        $sales = $this->createStub(GroupSalesRepository::class);
        $sales->method('totals')->willReturn(['ORDERS' => 0, 'FAMILIES' => 0, 'PRINTS' => 0, 'DIGITAL' => 0, 'BUNDLES' => 0, 'PAID_TOTAL' => 0]);
        $sales->method('printsByProduct')->willReturn(new Result());
        $output = (new GetGroupSalesUseCase(new SalesAccess($this->actor('organizer', [], [])), $this->directory($this->group()), $sales))->execute(5, self::GROUP);

        self::assertSame([0, 0, 0, 0, 0, 0, []], [$output->paidOrders, $output->families, $output->prints, $output->digitalFiles, $output->bundles, $output->paidTotal, $output->products]);
    }

    /**
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     */
    private function actor(string $role, array $institutionIds, array $groupIds): StaffRequestAccessInterface
    {
        $access = $this->createStub(StaffRequestAccessInterface::class);
        $access->method('actor')->willReturn(new StaffRequestActorOutputDto(5, 'Сотрудник', $role, 1, $institutionIds, $groupIds));

        return $access;
    }

    private function directory(?GroupDirectoryItemOutputDto $group): GroupDirectoryInterface
    {
        $groups = $this->createStub(GroupDirectoryInterface::class);
        $groups->method('find')->willReturn($group);

        return $groups;
    }

    private function group(): GroupDirectoryItemOutputDto
    {
        return new GroupDirectoryItemOutputDto(
            nativeId: 11,
            id: self::GROUP,
            name: 'Солнышко',
            kind: 'children',
            institutionNativeId: 7,
            institutionId: 'institution',
            institutionName: 'Детский сад',
            shootNativeId: 2,
            shootId: 'shoot',
            shootName: 'Осень',
            calendar: new GroupCalendarOutputDto('Europe/Moscow', null, null, null, null, 'preparing'),
        );
    }

    /** @param list<array<string, string>> $rows */
    private function rows(array $rows): Result
    {
        return new class($rows) extends Result {
            /** @param list<array<string, string>> $rows */
            public function __construct(private array $rows) {}

            public function fetch(): array|false
            {
                return array_shift($this->rows) ?? false;
            }
        };
    }
}
