<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Presentation\Order\Dto\CreateOrderRequestDto;
use Morefoto\Commerce\Presentation\Order\Dto\OrderBuyerRequestDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderListRequestDto;
use Morefoto\Commerce\Presentation\Order\OrderInputMapper;
use Morefoto\Commerce\Presentation\Storefront\Dto\QuoteLineRequestDto;
use Morefoto\Commerce\Presentation\Storefront\StorefrontMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Presentation\Consent\AcceptedDocumentInputMapper;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderInputMapperTest extends TestCase
{
    public function testOrderBuyerNeedsOnlyTheEmailAndStillAcceptsAnOlderBody(): void
    {
        // E8-T04: the strict body needs only the email; a page opened before E8 still sends name and phone.
        $mapper = new OrderInputMapper(new StorefrontMapper(), new AcceptedDocumentInputMapper());
        $line = new QuoteLineRequestDto('11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 1);
        $order = static fn(OrderBuyerRequestDto $buyer): CreateOrderRequestDto => new CreateOrderRequestDto(str_repeat('a', 64), [$line], $buyer, str_repeat('c', 64), str_repeat('b', 32));
        $buyer = static fn(array $values): OrderBuyerRequestDto => ArrayToDtoMapper::map(StrictRequestValues::normalize($values, OrderBuyerRequestDto::class, true), OrderBuyerRequestDto::class);

        $current = $mapper->create($order($buyer(['email' => 'parent@example.ru', 'reviewed' => true])))->buyer;
        $older = $mapper->create($order($buyer(['name' => 'Анна', 'phone' => '+79001234567', 'email' => 'parent@example.ru', 'reviewed' => true])))->buyer;

        self::assertSame(['', '', 'parent@example.ru'], [$current->name, $current->phone, $current->email]);
        self::assertSame(['Анна', '+79001234567'], [$older->name, $older->phone], 'Kept only for the replay hash of an attempt begun before E8.');
    }

    public function testEmptyFiltersAreAbsentAndValidOnesPassThrough(): void
    {
        $input = (new OrderInputMapper(new StorefrontMapper(), new AcceptedDocumentInputMapper()))->search(new StaffOrderListRequestDto(
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
        self::assertNull($input->latePayment);
    }

    public function testLatePaymentFilterIsAvailableSinceG1(): void
    {
        $mapper = new OrderInputMapper(new StorefrontMapper(), new AcceptedDocumentInputMapper());

        self::assertTrue($mapper->search(new StaffOrderListRequestDto(late: 'true'))->latePayment);
        self::assertFalse($mapper->search(new StaffOrderListRequestDto(late: 'false'))->latePayment);
    }

    #[DataProvider('invalid')]
    public function testRejectsUnavailableAndInvalidFilters(StaffOrderListRequestDto $request, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        (new OrderInputMapper(new StorefrontMapper(), new AcceptedDocumentInputMapper()))->search($request);
    }

    public static function invalid(): iterable
    {
        yield 'late is a boolean' => [new StaffOrderListRequestDto(late: 'yes'), 'INVALID_FILTER'];
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
