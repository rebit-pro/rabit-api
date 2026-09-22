<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Order\Dto\CreateOrderInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderBuyerInputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderStaffScopeOutputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Application\Order\Service\CheckoutReceipts;
use Morefoto\Commerce\Application\Order\Service\CheckoutRequestHash;
use Morefoto\Commerce\Application\Order\Service\OrderReader;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderCheckoutRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Infrastructure\Order\CheckoutKeySeal;
use Morefoto\Commerce\Infrastructure\Order\OrderStaffAccess;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderReceiptsAndAccessTest extends TestCase
{
    private const string LINE_A = '11111111-1111-4111-8111-111111111111';
    private const string LINE_B = '22222222-2222-4222-8222-222222222222';
    private const string PRODUCT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    public function testRequestHashIgnoresLineOrderButNotBody(): void
    {
        $hash = new CheckoutRequestHash();

        self::assertSame($hash->hash($this->input([self::LINE_A, self::LINE_B])), $hash->hash($this->input([self::LINE_B, self::LINE_A])));
        self::assertNotSame($hash->hash($this->input([self::LINE_A])), $hash->hash($this->input([self::LINE_A], 'Другое имя')));
    }

    public function testSameKeyWithAnotherBodyIsConflict(): void
    {
        $repository = $this->createStub(OrderCheckoutRepository::class);
        $repository->method('reserve')->willReturn(['REQUEST_HASH' => str_repeat('0', 64), 'ORDER_ID' => null, 'SEALED_KEY' => null]);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $this->receipts($repository)->reserve(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $this->input([self::LINE_A]));
    }

    public function testFirstReservationContinuesAndReplayOpensTheSameKey(): void
    {
        $input = $this->input([self::LINE_A]);
        $requestHash = (new CheckoutRequestHash())->hash($input);
        $accessKey = str_repeat('f', 64);
        $sealed = (new CheckoutKeySeal())->seal($accessKey, str_repeat('b', 32), hash('sha256', str_repeat('a', 64)) . '|7');
        $repository = $this->createStub(OrderCheckoutRepository::class);
        $repository->method('reserve')->willReturnOnConsecutiveCalls(
            ['REQUEST_HASH' => $requestHash, 'ORDER_ID' => null, 'SEALED_KEY' => null],
            ['REQUEST_HASH' => $requestHash, 'ORDER_ID' => '7', 'SEALED_KEY' => $sealed],
        );
        $keys = $this->createMock(OrderAccessKeyRepository::class);
        $keys->expects(self::once())->method('expiresAt')->with(7, hash('sha256', $accessKey))->willReturn('2026-10-22 10:00:00');
        $receipts = $this->receipts($repository, $keys, $this->reader());

        $first = $receipts->reserve(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $input);
        $replay = $receipts->reserve(str_repeat('a', 64), new IdempotencyKey(str_repeat('b', 32)), $input);

        self::assertNull($first);

        self::assertNotNull($replay);
        self::assertSame($accessKey, $replay->accessKey);
        self::assertSame('2026-10-22T13:00:00+03:00', $replay->accessKeyExpiresAt);
        self::assertSame('MF-000007', $replay->order->number);
    }

    public function testStaffScopeFollowsD08AndMapsAccessFailures(): void
    {
        self::assertNull($this->access(new InstitutionScopeOutputDto('organizer', 3, []))->scope(1)->institutionIds);
        self::assertSame([4, 9], $this->access(new InstitutionScopeOutputDto('curator', 3, [9, 4, 9]))->scope(1)->institutionIds);
        foreach ([
            [new InstitutionScopeOutputDto('head', 3, [4]), 'FORBIDDEN'],
            [new HttpException('Action is forbidden.', 403), 'FORBIDDEN'],
            [new HttpException('Unauthorized', 401), 'UNAUTHORIZED'],
        ] as [$source, $code]) {
            try {
                $this->access($source)->scope(1);
                self::fail($code . ' expected.');
            } catch (HttpException $error) {
                self::assertSame($code, $error->getMessage());
            }
        }
    }

    public function testChangedAssignmentsDuringReadAreRejected(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('ACCESS_CHANGED');
        $this->access(new InstitutionScopeOutputDto('curator', 4, [4]))->assertUnchanged(1, new OrderStaffScopeOutputDto('curator', 3, [4]));
    }

    private function access(HttpException|InstitutionScopeOutputDto $source): OrderStaffAccess
    {
        $institutions = $this->createStub(InstitutionAccessInterface::class);
        if ($source instanceof HttpException) {
            $institutions->method('scope')->willThrowException($source);
        } else {
            $institutions->method('scope')->willReturn($source);
        }

        return new OrderStaffAccess($institutions);
    }

    private function receipts(OrderCheckoutRepository $repository, ?OrderAccessKeyRepository $keys = null, ?OrderReader $reader = null): CheckoutReceipts
    {
        return new CheckoutReceipts(
            $repository,
            new CheckoutRequestHash(),
            new CheckoutKeySeal(),
            $reader ?? $this->createStub(OrderReader::class),
            $keys ?? $this->createStub(OrderAccessKeyRepository::class),
            new OrderCalendarPolicy(),
        );
    }

    private function reader(): OrderReader
    {
        $order = $this->createStub(Result::class);
        $order->method('fetch')->willReturn(OrderMapperTest::row());
        $lines = $this->createStub(Result::class);
        $lines->method('fetch')->willReturnOnConsecutiveCalls(OrderMapperTest::line(), false);
        $orders = $this->createStub(OrderRepository::class);
        $orders->method('find')->willReturn($order);
        $orders->method('lines')->willReturn($lines);

        return new OrderReader($orders, new OrderRowMapper(new OrderCalendarPolicy()));
    }

    /** @param list<string> $assignments */
    private function input(array $assignments, string $name = 'Анна'): CreateOrderInputDto
    {
        $lines = [];
        foreach ($assignments as $assignment) {
            $lines[] = new QuoteLineInputDto($assignment, self::PRODUCT, 1);
        }

        return new CreateOrderInputDto(str_repeat('c', 64), $lines, new OrderBuyerInputDto($name, '+79001234567', 'buyer@example.test', '', null, true));
    }
}
