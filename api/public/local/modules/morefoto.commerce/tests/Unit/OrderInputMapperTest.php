<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderListRequestDto;
use Morefoto\Commerce\Presentation\Order\OrderInputMapper;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderInputMapperTest extends TestCase
{
    public function testEmptyFiltersAreAbsentAndValidOnesPassThrough(): void
    {
        $input = (new OrderInputMapper(new StorefrontMapper()))->search(new StaffOrderListRequestDto(
            q: '  MF-0001 ',
            institutionId: '',
            groupId: '11111111-1111-4111-8111-111111111111',
            paymentStatus: 'unpaid',
            productionStatus: 'not-started',
            dateFrom: '2026-09-01',
            dateTo: '2026-09-30',
            page: 2,
            pageSize: 100,
        ));

        self::assertSame('MF-0001', $input->query);
        self::assertNull($input->institutionId);
        self::assertSame('11111111-1111-4111-8111-111111111111', $input->groupId);
        self::assertSame(2, $input->page);
    }

    #[DataProvider('invalid')]
    public function testRejectsUnavailableAndInvalidFilters(StaffOrderListRequestDto $request, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        (new OrderInputMapper(new StorefrontMapper()))->search($request);
    }

    public static function invalid(): iterable
    {
        yield 'late before G1' => [new StaffOrderListRequestDto(late: 'true'), 'FILTER_UNAVAILABLE'];
        yield 'settlement before I1' => [new StaffOrderListRequestDto(settlement: 'refund'), 'FILTER_UNAVAILABLE'];
        yield 'page size above limit' => [new StaffOrderListRequestDto(pageSize: 101), 'INVALID_PAGE'];
        yield 'zero page' => [new StaffOrderListRequestDto(page: 0), 'INVALID_PAGE'];
        yield 'one-letter query' => [new StaffOrderListRequestDto(q: 'a'), 'INVALID_FILTER'];
        yield 'unknown payment status' => [new StaffOrderListRequestDto(paymentStatus: 'refunded'), 'INVALID_FILTER'];
        yield 'unknown production status' => [new StaffOrderListRequestDto(productionStatus: 'shipped'), 'INVALID_FILTER'];
        yield 'bad institution id' => [new StaffOrderListRequestDto(institutionId: 'institution'), 'INVALID_FILTER'];
        yield 'impossible date' => [new StaffOrderListRequestDto(dateFrom: '2026-02-30'), 'INVALID_FILTER'];
        yield 'reversed dates' => [new StaffOrderListRequestDto(dateFrom: '2026-09-30', dateTo: '2026-09-01'), 'INVALID_FILTER'];
    }
}
