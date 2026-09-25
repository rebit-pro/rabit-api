<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Infrastructure\Payment\OrderPayments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Контракт OrderPaymentInterface: paid окончателен, повтор статуса не меняет версию, срок приёма в UTC.
 *
 * @internal
 */
final class OrderPaymentsTest extends TestCase
{
    #[DataProvider('transitions')]
    public function testPaymentStatusTransitions(string $current, OrderPaymentInputDto $input, bool $written): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->method('find')->willReturn($this->rows(['PAYMENT_STATUS' => $current, 'PAID_AT' => null]));
        $orders->expects($written ? self::once() : self::never())->method('applyPayment')
            ->with(7, $input->status, $input->paidAt, $input->latePayment)
        ;

        $this->payments($orders)->applyPayment($input);
    }

    public static function transitions(): iterable
    {
        yield 'unpaid → pending' => ['unpaid', new OrderPaymentInputDto(7, 'pending'), true];
        yield 'pending again keeps the version' => ['pending', new OrderPaymentInputDto(7, 'pending'), false];
        yield 'declined → pending' => ['declined', new OrderPaymentInputDto(7, 'pending'), true];
        yield 'pending → paid late' => ['pending', new OrderPaymentInputDto(7, 'paid', '2026-09-25 12:00:00', true), true];
        yield 'paid never goes back' => ['paid', new OrderPaymentInputDto(7, 'declined'), false];
        yield 'paid is not paid twice' => ['paid', new OrderPaymentInputDto(7, 'paid', '2026-09-26 12:00:00'), false];
    }

    public function testPaidNeedsItsMomentAndUnpaidIsNotAPaymentStatus(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->expects(self::never())->method('applyPayment');

        foreach ([new OrderPaymentInputDto(7, 'paid'), new OrderPaymentInputDto(7, 'unpaid'), new OrderPaymentInputDto(7, 'pending', '2026-09-25 12:00:00')] as $input) {
            try {
                $this->payments($orders)->applyPayment($input);
                self::fail('Expected a refusal for ' . $input->status);
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLockedOrderCarriesTotalsAndCloseInUtc(): void
    {
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('lock')->willReturn($this->rows([
            'ID' => '7', 'PUBLIC_ID' => 'o-7', 'NUMBER' => 'MF-0007', 'INSTITUTION_ID' => '3', 'INSTITUTION_NAME' => 'Сад', 'GROUP_NAME' => 'Солнышко',
            'GROUP_PUBLIC_ID' => 'g-1', 'SUBTOTAL' => '110000', 'DISCOUNT' => '5000', 'GIFT_SAVING' => '0', 'TOTAL' => '105000',
            'PAYMENT_STATUS' => 'pending', 'VERSION' => '4', 'PAID_AT' => null, 'LATE_PAYMENT' => '0',
        ]));

        $order = $this->payments($orders, '2026-10-01T00:00:00+03:00')->lock(7);

        self::assertSame([7, 3, 105000, '4', 'pending', false], [$order->id, $order->institutionId, $order->total, $order->version, $order->paymentStatus, $order->latePayment]);
        self::assertSame('2026-09-30 21:00:00', $order->closesAt);
    }

    public function testMalformedOrMissingKeyIsNotFound(): void
    {
        $keys = $this->createStub(OrderAccessKeyRepository::class);
        $keys->method('findActive')->willReturn(false);
        $payments = new OrderPayments($keys, $this->createStub(OrderRepository::class), $this->createStub(GroupCalendarInterface::class), $this->clock());

        foreach ([null, 'short', str_repeat('a', 64)] as $key) {
            try {
                $payments->byKey($key);
                self::fail('Expected ORDER_NOT_FOUND');
            } catch (HttpException $error) {
                self::assertSame(['ORDER_NOT_FOUND', 404], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    private function payments(OrderRepository $orders, ?string $closesAt = null): OrderPayments
    {
        $calendars = $this->createStub(GroupCalendarInterface::class);
        $calendars->method('get')->willReturn(new CalendarMutationOutputDto('g-1', 1, new GroupCalendarOutputDto('Europe/Moscow', null, $closesAt, null, 'open')));

        return new OrderPayments($this->createStub(OrderAccessKeyRepository::class), $orders, $calendars, $this->clock());
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC')));

        return $clock;
    }

    /** @param array<string, mixed> $row */
    private function rows(array $row): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($row);

        return $result;
    }
}
