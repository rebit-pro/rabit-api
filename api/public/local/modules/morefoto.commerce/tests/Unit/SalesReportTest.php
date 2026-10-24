<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Application\Sales\Mapper\SalesReportRowMapper;
use Morefoto\Commerce\Application\Sales\Service\SalesReportScope;
use Morefoto\Commerce\Application\Sales\UseCase\GetChildPreviewUseCase;
use Morefoto\Commerce\Application\Sales\UseCase\GetSalesReportUseCase;
use Morefoto\Commerce\Domain\Sales\Enum\ChildSalesStatusEnum;
use Morefoto\Commerce\Domain\Sales\Enum\SalesBreakdownEnum;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;
use Morefoto\Commerce\Domain\Sales\Service\ChildSalesStatusPolicy;
use Morefoto\Commerce\Domain\Sales\ValueObject\SalesArea;
use Morefoto\Commerce\Infrastructure\Sales\SalesAccess;
use Morefoto\Commerce\Presentation\Sales\SalesReportResultMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Media\Dto\GroupChildOutputDto;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * E10: COM-15/COM-17 — the sales report of each staff role in its own area.
 *
 * @internal
 */
final class SalesReportTest extends TestCase
{
    private const string GROUP = '0f8fad5b-d9cb-469f-a165-70867728950e';
    private const string OTHER_GROUP = '1f8fad5b-d9cb-469f-a165-70867728950e';
    private const string SHOOT = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    private const string INSTITUTION = '9b2f1c4e-3a5d-4e6f-8a7b-1c2d3e4f5a6b';

    /**
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     */
    #[DataProvider('scopes')]
    public function testOnlyTheOwnAreaSeesTheGroup(string $role, array $institutionIds, array $groupIds, ?string $error): void
    {
        $useCase = $this->useCase($this->actor($role, $institutionIds, $groupIds), $this->directory([$this->group()]), $this->sales([]), $this->media([], []));

        try {
            $report = $useCase->execute(5, new SalesReportInputDto(groupId: self::GROUP));
            self::assertNull($error);
            self::assertSame(SalesReportLevelEnum::GROUP, $report->level);
            self::assertSame('Солнышко', $report->group?->name);
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
        yield 'head of the institution' => ['head', [7], [], null];
        yield 'head of another institution' => ['head', [4], [], 'GROUP_NOT_FOUND'];
        yield 'teacher of the group' => ['teacher', [], [11], null];
        yield 'teacher of another group' => ['teacher', [], [12], 'GROUP_NOT_FOUND'];
        yield 'unknown role' => ['parent', [], [], 'FORBIDDEN'];
    }

    public function testAMismatchedAncestorOrAMalformedIdLooksLikeAForeignObject(): void
    {
        $useCase = $this->useCase($this->actor('organizer', [], []), $this->directory([$this->group()]), $this->sales([]), $this->media([], []));

        foreach ([
            [new SalesReportInputDto(shootId: self::INSTITUTION, groupId: self::GROUP), 'GROUP_NOT_FOUND'],
            [new SalesReportInputDto(institutionId: self::SHOOT, groupId: self::GROUP), 'GROUP_NOT_FOUND'],
            [new SalesReportInputDto(groupId: 'not-a-uuid'), 'GROUP_NOT_FOUND'],
            [new SalesReportInputDto(shootId: 'x'), 'SHOOT_NOT_FOUND'],
        ] as [$input, $code]) {
            try {
                $useCase->execute(5, $input);
                self::fail($code);
            } catch (HttpException $exception) {
                self::assertSame([$code, 404], [$exception->getMessage(), $exception->getCode()]);
            }
        }
    }

    public function testAnIdTheDirectoryWouldRejectIsNotFoundWithoutReadingTheDirectory(): void
    {
        $directory = $this->createMock(GroupDirectoryInterface::class);
        $directory->expects(self::never())->method('page');
        $directory->expects(self::never())->method('find');
        $useCase = $this->useCase($this->actor('organizer', [], []), $directory, $this->sales([]), $this->media([], []));

        // Hex-shaped but neither UUID version 1–5 nor variant 8–b: Organization would throw on it outside its try.
        foreach ([
            [new SalesReportInputDto(institutionId: '00000000-0000-0000-0000-000000000000'), 'INSTITUTION_NOT_FOUND'],
            [new SalesReportInputDto(shootId: '7c9e6679-7425-90de-944b-e07fc1f90ae7'), 'SHOOT_NOT_FOUND'],
            [new SalesReportInputDto(groupId: '0f8fad5b-d9cb-469f-c165-70867728950e'), 'GROUP_NOT_FOUND'],
        ] as [$input, $code]) {
            try {
                $useCase->execute(5, $input);
                self::fail($code);
            } catch (HttpException $exception) {
                self::assertSame([$code, 404], [$exception->getMessage(), $exception->getCode()]);
            }
        }
    }

    public function testTheTeacherShootReportHasOnlyTheTeacherGroupsWithZeroRows(): void
    {
        $queries = [];
        $directory = $this->directory([$this->group(), $this->group(12, self::OTHER_GROUP, 'Ёжик')], $queries);
        $sales = $this->sales([
            'group' => [$this->row(self::GROUP, 'Солнышко', orders: 3, paidOrders: 2, families: 2, paidFamilies: 2, prints: 5, total: 150_000)],
            'total' => [$this->row('', '', orders: 3, paidOrders: 2, families: 2, paidFamilies: 2, prints: 5, total: 150_000)],
        ]);
        $report = $this->useCase($this->actor('teacher', [], [11, 12]), $directory, $sales, $this->media([], [11 => 10, 12 => 8]))
            ->execute(5, new SalesReportInputDto(shootId: self::SHOOT))
        ;

        self::assertSame([11, 12], $queries[0]->groupIds);
        self::assertSame(self::SHOOT, $queries[0]->shootId);
        self::assertSame(SalesReportLevelEnum::SHOOT, $report->level);
        self::assertSame(['Осень', 'Детский сад', null], [$report->shoot?->name, $report->institution?->name, $report->group]);
        self::assertSame([[self::GROUP, 'Солнышко', 10, 2, 150_000], [self::OTHER_GROUP, 'Ёжик', 8, 0, 0]], array_map(
            static fn($row): array => [$row->id, $row->name, $row->totals->children, $row->totals->paidOrders, $row->totals->paidTotal],
            $report->rows,
        ));
        self::assertSame([18, 2, 5, 150_000], [$report->totals->children, $report->totals->paidFamilies, $report->totals->prints, $report->totals->paidTotal]);
    }

    public function testAMissingShootIsNotFound(): void
    {
        $useCase = $this->useCase($this->actor('head', [7], []), $this->directory([]), $this->sales([]), $this->media([], []));

        $this->expectExceptionObject(new HttpException('SHOOT_NOT_FOUND', 404));
        $useCase->execute(5, new SalesReportInputDto(shootId: self::SHOOT));
    }

    public function testTheAllLevelSortsInstitutionsByRussianName(): void
    {
        $groups = [
            $this->group(11, self::GROUP, 'Солнышко', 7, 'inst-ya', 'Ясли № 5'),
            $this->group(12, self::OTHER_GROUP, 'Ёжик', 8, 'inst-be', 'Берёзка'),
            $this->group(13, '2f8fad5b-d9cb-469f-a165-70867728950e', 'Пчёлка', 9, 'inst-ai', 'Аист'),
        ];
        $report = $this->useCase($this->actor('curator', [7, 8, 9], []), $this->directory($groups), $this->sales([]), $this->media([], [11 => 3, 13 => 4]))
            ->execute(5, new SalesReportInputDto())
        ;

        self::assertSame(SalesReportLevelEnum::ALL, $report->level);
        self::assertNull($report->institution);
        self::assertSame([['Аист', 4], ['Берёзка', 0], ['Ясли № 5', 3]], array_map(static fn($row): array => [$row->name, $row->totals->children], $report->rows));
    }

    public function testGroupChildrenKeepStatusesCodesAndTransferredOrders(): void
    {
        $children = [
            new GroupChildOutputDto(101, 'child-b', 11, 'B', null),
            new GroupChildOutputDto(100, 'child-a', 11, 'A', 'photo-a'),
            new GroupChildOutputDto(102, 'child-aa', 11, 'AA', 'photo-aa'),
            new GroupChildOutputDto(103, 'child-c', 11, 'C', null),
        ];
        $sales = $this->sales(['child' => [
            $this->row('100', 'A', orders: 2, paidOrders: 1, prints: 4, total: 90_050) + ['CHILD_PUBLIC_ID' => 'child-a'],
            $this->row('103', 'C', orders: 1, late: 1) + ['CHILD_PUBLIC_ID' => 'child-c'],
            $this->row('104', 'D', orders: 1) + ['CHILD_PUBLIC_ID' => 'child-moved'],
        ]]);

        foreach ([null => [null, null, null], '2026-10-01T10:00:00+03:00' => ['photo-a', null, 'photo-aa']] as $sentAt => $photos) {
            $report = $this->useCase($this->actor('teacher', [], [11]), $this->directory([$this->group(sentAt: '' === $sentAt ? null : $sentAt)]), $sales, $this->media($children, []))
                ->execute(5, new SalesReportInputDto(groupId: self::GROUP))
            ;

            self::assertSame(['A', 'B', 'C', 'D', 'AA'], array_map(static fn($child): string => $child->code, $report->children));
            self::assertSame(
                [ChildSalesStatusEnum::PAID, ChildSalesStatusEnum::NONE, ChildSalesStatusEnum::LATE, ChildSalesStatusEnum::UNPAID, ChildSalesStatusEnum::NONE],
                array_map(static fn($child): ChildSalesStatusEnum => $child->status, $report->children),
            );
            self::assertSame($photos, [$report->children[0]->photoId, $report->children[3]->photoId, $report->children[4]->photoId]);
            self::assertSame(['child-moved', 4], [$report->children[3]->id, $report->totals->children]);
        }

        $result = (new SalesReportResultMapper())->report($report);
        self::assertSame(
            ['id', 'code', 'status', 'orders', 'paidOrders', 'prints', 'digitalFiles', 'bundles', 'paidTotal', 'hasPreview'],
            array_keys(get_object_vars($result->children[0])),
        );
        self::assertSame(['paid', true, 90_050], [$result->children[0]->status, $result->children[0]->hasPreview, $result->children[0]->paidTotal]);
    }

    public function testTheStatusPolicyPrefersPaymentOverLatenessOverAnUnpaidOrder(): void
    {
        $policy = new ChildSalesStatusPolicy();

        self::assertSame(ChildSalesStatusEnum::PAID, $policy->status(3, 1, 1));
        self::assertSame(ChildSalesStatusEnum::LATE, $policy->status(2, 0, 1));
        self::assertSame(ChildSalesStatusEnum::UNPAID, $policy->status(1, 0, 0));
        self::assertSame(ChildSalesStatusEnum::NONE, $policy->status(0, 0, 0));
    }

    public function testThePreviewNeedsTheAreaAndATransmittedGroup(): void
    {
        $media = $this->createStub(GroupChildrenInterface::class);
        $media->method('find')->willReturn(new GroupChildOutputDto(100, 'child-a', 11, 'A', 'photo-a'));
        $media->method('thumbnail')->willReturn(new PreviewContentOutputDto('webp'));
        $sent = $this->directory([$this->group(sentAt: '2026-10-01T10:00:00+03:00')]);

        self::assertSame('webp', (new GetChildPreviewUseCase(new SalesAccess($this->actor('teacher', [], [11])), $sent, $media))->execute(5, 'child-a')->content);
        foreach ([
            [$this->actor('teacher', [], [12]), $sent, 'CHILD_NOT_FOUND'],
            [$this->actor('head', [4], []), $this->directory([]), 'CHILD_NOT_FOUND'],
            [$this->actor('organizer', [], []), $this->directory([$this->group()]), 'PREVIEW_UNAVAILABLE'],
        ] as [$actor, $directory, $code]) {
            try {
                (new GetChildPreviewUseCase(new SalesAccess($actor), $directory, $media))->execute(5, 'child-a');
                self::fail($code);
            } catch (HttpException $exception) {
                self::assertSame([$code, 404], [$exception->getMessage(), $exception->getCode()]);
            }
        }
    }

    public function testAMissingChildOrFrameIsNotFound(): void
    {
        $sent = $this->directory([$this->group(sentAt: '2026-10-01T10:00:00+03:00')]);
        $organizer = new SalesAccess($this->actor('organizer', [], []));
        $cases = [
            'unknown child' => [null, new PreviewContentOutputDto('webp'), 'CHILD_NOT_FOUND'],
            'child without a ready frame' => [new GroupChildOutputDto(100, 'child-a', 11, 'A', null), new PreviewContentOutputDto('webp'), 'PREVIEW_UNAVAILABLE'],
            'frame without a preview file' => [new GroupChildOutputDto(100, 'child-a', 11, 'A', 'photo-a'), null, 'PREVIEW_UNAVAILABLE'],
        ];
        foreach ($cases as $name => [$child, $thumbnail, $code]) {
            $media = $this->createStub(GroupChildrenInterface::class);
            $media->method('find')->willReturn($child);
            $media->method('thumbnail')->willReturn($thumbnail);
            try {
                (new GetChildPreviewUseCase($organizer, $sent, $media))->execute(5, 'child-a');
                self::fail($name);
            } catch (HttpException $exception) {
                self::assertSame([$code, 404], [$exception->getMessage(), $exception->getCode()]);
            }
        }
    }

    private function useCase(StaffRequestAccessInterface $actor, GroupDirectoryInterface $directory, SalesReportRepository $sales, GroupChildrenInterface $media): GetSalesReportUseCase
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-24T12:00:00+00:00'));

        return new GetSalesReportUseCase(
            new SalesReportScope(new SalesAccess($actor), $directory),
            $sales,
            $media,
            new SalesReportRowMapper(new ChildSalesStatusPolicy()),
            $clock,
        );
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

    /**
     * @param list<GroupDirectoryItemOutputDto>      $groups
     * @param null|list<GroupDirectoryQueryInputDto> $queries
     *
     * @param-out list<GroupDirectoryQueryInputDto> $queries
     */
    private function directory(array $groups, ?array &$queries = null): GroupDirectoryInterface
    {
        $queries = [];
        $directory = $this->createStub(GroupDirectoryInterface::class);
        $directory->method('find')->willReturnCallback(static function(string $id) use ($groups): ?GroupDirectoryItemOutputDto {
            foreach ($groups as $group) {
                if ($id === $group->id) {
                    return $group;
                }
            }

            return null;
        });
        $directory->method('page')->willReturnCallback(static function(GroupDirectoryQueryInputDto $query) use ($groups, &$queries): GroupDirectoryPageOutputDto {
            $queries[] = $query;
            $items = array_values(array_filter($groups, static fn(GroupDirectoryItemOutputDto $group): bool => (null === $query->institutionIds || in_array($group->institutionNativeId, $query->institutionIds, true))
                && (null === $query->groupIds || in_array($group->nativeId, $query->groupIds, true))));

            return new GroupDirectoryPageOutputDto(array_slice($items, 0, $query->pageSize), count($items));
        });

        return $directory;
    }

    /** @param array<string, list<array<string, int|string>>> $rows keyed by breakdown value or `total` */
    private function sales(array $rows): SalesReportRepository
    {
        $sales = $this->createStub(SalesReportRepository::class);
        $sales->method('aggregate')->willReturnCallback(fn(SalesArea $area, ?SalesBreakdownEnum $breakdown): Result => $this->fetched($rows[$breakdown->value ?? 'total'] ?? []));
        $sales->method('products')->willReturnCallback(fn(): Result => $this->fetched([]));

        return $sales;
    }

    /**
     * @param list<GroupChildOutputDto> $children
     * @param array<int, int>           $counts
     */
    private function media(array $children, array $counts): GroupChildrenInterface
    {
        $media = $this->createStub(GroupChildrenInterface::class);
        $media->method('children')->willReturn($children);
        $media->method('counts')->willReturn($counts);

        return $media;
    }

    /** @return array<string, int|string> */
    private function row(string $id, string $name, int $orders = 0, int $paidOrders = 0, int $late = 0, int $families = 0, int $paidFamilies = 0, int $prints = 0, int $total = 0): array
    {
        return [
            'ROW_ID' => $id, 'NAME' => $name, 'ORDERS' => $orders, 'PAID_ORDERS' => $paidOrders, 'LATE_ORDERS' => $late,
            'ORDERED_FAMILIES' => $families, 'PAID_FAMILIES' => $paidFamilies, 'PRINTS' => $prints, 'DIGITAL' => 0, 'BUNDLES' => 0, 'PAID_TOTAL' => $total,
        ];
    }

    private function group(int $nativeId = 11, string $id = self::GROUP, string $name = 'Солнышко', int $institutionNativeId = 7, string $institutionId = self::INSTITUTION, string $institutionName = 'Детский сад', ?string $sentAt = null): GroupDirectoryItemOutputDto
    {
        return new GroupDirectoryItemOutputDto(
            nativeId: $nativeId,
            id: $id,
            name: $name,
            kind: 'children',
            institutionNativeId: $institutionNativeId,
            institutionId: $institutionId,
            institutionName: $institutionName,
            shootNativeId: 2,
            shootId: self::SHOOT,
            shootName: 'Осень',
            calendar: new GroupCalendarOutputDto('Europe/Moscow', $sentAt, null, null, null, null === $sentAt ? 'preparing' : 'open'),
        );
    }

    /** @param list<array<string, int|string>> $rows */
    private function fetched(array $rows): Result
    {
        return new class($rows) extends Result {
            /** @param list<array<string, int|string>> $rows */
            public function __construct(private array $rows) {}

            public function fetch(): array|false
            {
                return array_shift($this->rows) ?? false;
            }
        };
    }
}
