<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Contract\OrderTransactionInterface;
use Morefoto\Commerce\Application\Order\Dto\OrderStaffScopeOutputDto;
use Morefoto\Commerce\Application\Order\UseCase\DeleteOrderUseCase;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Payment\OrderPaymentRemovalInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * Removal of an order without money (#92 DEC-02, DEC-06).
 *
 * @internal
 */
final class DeleteOrderUseCaseTest extends TestCase
{
    private const string ORDER = '12345678-abcd-4abc-8abc-123456789abc';

    #[DataProvider('withoutMoney')]
    public function testOrganizerRemovesOrderWithoutMoney(string $status): void
    {
        $orders = $this->orders($status);
        $payments = $this->createMock(OrderPaymentRemovalInterface::class);
        $payments->expects(self::once())->method('remove')->with(42);
        $orders->expects(self::once())->method('delete')->with(42);

        $this->useCase($orders, $payments, 'organizer')->execute(12, self::ORDER);
    }

    public static function withoutMoney(): iterable
    {
        yield 'never paid' => ['unpaid'];
        yield 'declined' => ['declined'];
    }

    #[DataProvider('withMoney')]
    public function testOrderWithMoneyStays(string $status, string $code): void
    {
        $orders = $this->orders($status);
        $payments = $this->createMock(OrderPaymentRemovalInterface::class);
        $payments->expects(self::never())->method('remove');
        $orders->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException($code, 409));
        $this->useCase($orders, $payments, 'organizer')->execute(12, self::ORDER);
    }

    public static function withMoney(): iterable
    {
        yield 'paid' => ['paid', 'ORDER_PAID'];
        yield 'payment in progress' => ['pending', 'PAYMENT_IN_PROGRESS'];
    }

    public function testCuratorOnlyReadsOrders(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $orders->expects(self::never())->method('lockPublic');

        $this->expectExceptionObject(new HttpException('FORBIDDEN', 403));
        $this->useCase($orders, $this->createStub(OrderPaymentRemovalInterface::class), 'curator')->execute(21, self::ORDER);
    }

    public function testRemovedOrderIsNotFound(): void
    {
        $orders = $this->createMock(OrderRepository::class);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(false);
        $orders->method('lockPublic')->with(self::ORDER)->willReturn($result);
        $orders->expects(self::never())->method('delete');

        $this->expectExceptionObject(new HttpException('ORDER_NOT_FOUND', 404));
        $this->useCase($orders, $this->createStub(OrderPaymentRemovalInterface::class), 'organizer')->execute(12, self::ORDER);
    }

    public function testRemovalControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/StaffOrderRemovalController.php',
            [
                'Morefoto\Commerce\Presentation\Controller',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                'Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse',
            ],
            [
                ['Morefoto\Commerce\Application\Order\UseCase\\', 'UseCase'],
                ['Morefoto\Commerce\Presentation\Order\Dto\\', 'RequestDto'],
            ],
        ));
    }

    private function orders(string $status): MockObject
    {
        $orders = $this->createMock(OrderRepository::class);
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(['ID' => '42', 'PAYMENT_STATUS' => $status]);
        $orders->method('lockPublic')->with(self::ORDER)->willReturn($result);

        return $orders;
    }

    private function useCase(MockObject $orders, OrderPaymentRemovalInterface $payments, string $role): DeleteOrderUseCase
    {
        self::assertInstanceOf(OrderRepository::class, $orders);
        $access = $this->createStub(OrderStaffAccessInterface::class);
        $access->method('scope')->willReturn(new OrderStaffScopeOutputDto($role, 1, 'organizer' === $role ? null : [10]));
        $transaction = $this->createStub(OrderTransactionInterface::class);
        $transaction->method('execute')->willReturnCallback(static fn(callable $operation): mixed => $operation());

        return new DeleteOrderUseCase($access, $orders, $payments, $transaction);
    }
}
