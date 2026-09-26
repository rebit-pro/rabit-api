<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Infrastructure\Files\OrderEntitlements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Право на файлы: digital даёт свой кадр, bundle и подарок — ребёнка целиком, печать — ничего; срок по D10.
 *
 * @internal
 */
final class OrderEntitlementsTest extends TestCase
{
    #[DataProvider('months')]
    public function testFilesMonthFollowsTheMoscowCalendar(string $paidAtUtc, string $untilUtc): void
    {
        self::assertSame($untilUtc, new OrderCalendarPolicy()->filesAvailableUntil(new \DateTimeImmutable($paidAtUtc, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
    }

    public static function months(): iterable
    {
        yield '31.01.2027 10:00 МСК → 28.02' => ['2027-01-31 07:00:00', '2027-02-28 07:00:00'];
        yield 'високосный 31.01.2028 → 29.02' => ['2028-01-31 07:00:00', '2028-02-29 07:00:00'];
        yield 'обычный день' => ['2026-09-26 09:30:00', '2026-10-26 09:30:00'];
        // 31.03 01:00 МСК — ещё 30.03 в UTC: месяц считается по Москве, а не по UTC.
        yield 'граница суток по Москве' => ['2027-03-30 22:00:00', '2027-04-29 22:00:00'];
        yield 'декабрь → январь' => ['2026-12-31 20:00:00', '2027-01-31 20:00:00'];
    }

    public function testCompositionAndDeadline(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('find')->willReturn($this->rows([[
            'ID' => '7', 'PUBLIC_ID' => 'o-7', 'NUMBER' => 'MF-0007', 'SHOOT_ID' => '4', 'PAYMENT_STATUS' => 'paid',
            'PAID_AT' => '2027-01-31 07:00:00', 'LATE_PAYMENT' => '0', 'GIFTS' => '["B"]',
        ]]));
        $orders->method('lines')->willReturn($this->rows([
            ['CHILD_ID' => '11', 'CHILD_CODE' => 'A', 'PHOTO_PUBLIC_ID' => 'p-1', 'PHOTO_CODE' => 'A-01', 'PRODUCT_KIND' => 'digital'],
            ['CHILD_ID' => '11', 'CHILD_CODE' => 'A', 'PHOTO_PUBLIC_ID' => 'p-2', 'PHOTO_CODE' => 'A-02', 'PRODUCT_KIND' => 'physical'],
            ['CHILD_ID' => '12', 'CHILD_CODE' => 'B', 'PHOTO_PUBLIC_ID' => 'p-3', 'PHOTO_CODE' => 'B-01', 'PRODUCT_KIND' => 'physical'],
            ['CHILD_ID' => '13', 'CHILD_CODE' => 'C', 'PHOTO_PUBLIC_ID' => null, 'PHOTO_CODE' => null, 'PRODUCT_KIND' => 'bundle'],
        ]));

        $order = $this->entitlements($orders)->byId(7);

        self::assertSame([7, 4, 'paid', false, '2027-02-28 07:00:00'], [$order->id, $order->shootId, $order->paymentStatus, $order->latePayment, $order->filesAvailableUntil]);
        self::assertSame([['p-1', 'A', 'A-01']], array_map(static fn($photo): array => [$photo->photoId, $photo->childCode, $photo->code], $order->photos));
        self::assertSame([12, 13], $order->childIds);
    }

    public function testUnpaidOrderHasNoDeadline(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('find')->willReturn($this->rows([[
            'ID' => '7', 'PUBLIC_ID' => 'o-7', 'NUMBER' => 'MF-0007', 'SHOOT_ID' => '4', 'PAYMENT_STATUS' => 'unpaid',
            'PAID_AT' => null, 'LATE_PAYMENT' => '0', 'GIFTS' => '[]',
        ]]));
        $orders->method('lines')->willReturn($this->rows([]));

        self::assertNull($this->entitlements($orders)->byId(7)->filesAvailableUntil);
    }

    public function testUnknownKeyIsNotFound(): void
    {
        $keys = $this->createStub(OrderAccessKeyRepository::class);
        $keys->method('findActive')->willReturn(false);
        $entitlements = new OrderEntitlements($keys, $this->createStub(OrderRepository::class), new OrderCalendarPolicy(), $this->clock());

        foreach ([null, 'bad', str_repeat('b', 64)] as $key) {
            try {
                $entitlements->byKey($key);
                self::fail('Expected ORDER_NOT_FOUND');
            } catch (HttpException $error) {
                self::assertSame(['ORDER_NOT_FOUND', 404], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    private function entitlements(OrderRepository $orders): OrderEntitlements
    {
        return new OrderEntitlements($this->createStub(OrderAccessKeyRepository::class), $orders, new OrderCalendarPolicy(), $this->clock());
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2027-02-01 12:00:00', new \DateTimeZone('UTC')));

        return $clock;
    }

    /** @param list<array<string, mixed>> $rows */
    private function rows(array $rows): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);

        return $result;
    }
}
