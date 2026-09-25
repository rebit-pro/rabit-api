<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Presentation\Payment\PaymentInputMapper;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentListRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentNotificationObjectRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentNotificationRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\StartPaymentRequestDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class PaymentInputMapperTest extends TestCase
{
    public function testRegistryFiltersPassThrough(): void
    {
        $input = new PaymentInputMapper()->search(new PaymentListRequestDto(status: 'succeeded', orderNumber: ' MF-0007 ', dateFrom: '2026-09-01', dateTo: '2026-09-30', late: 'true', page: 2, pageSize: 100));

        self::assertSame('succeeded', $input->status);
        self::assertSame('MF-0007', $input->orderNumber);
        self::assertTrue($input->latePayment);
        self::assertSame(2, $input->page);
    }

    #[DataProvider('invalidFilters')]
    public function testRegistryRejectsInvalidFilters(PaymentListRequestDto $request, string $code): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        new PaymentInputMapper()->search($request);
    }

    public static function invalidFilters(): iterable
    {
        yield 'unknown status' => [new PaymentListRequestDto(status: 'paid'), 'INVALID_FILTER'];
        yield 'late is boolean' => [new PaymentListRequestDto(late: '1'), 'INVALID_FILTER'];
        yield 'number with SQL' => [new PaymentListRequestDto(orderNumber: "MF-1' OR 1=1"), 'INVALID_FILTER'];
        yield 'impossible date' => [new PaymentListRequestDto(dateFrom: '2026-02-30'), 'INVALID_FILTER'];
        yield 'reversed dates' => [new PaymentListRequestDto(dateFrom: '2026-09-30', dateTo: '2026-09-01'), 'INVALID_FILTER'];
        yield 'page size' => [new PaymentListRequestDto(pageSize: 101), 'INVALID_PAGE'];
    }

    public function testNotificationKeepsOnlyEventAndPaymentId(): void
    {
        $input = new PaymentInputMapper()->notification(new PaymentNotificationRequestDto('yookassa', 'payment.succeeded', new PaymentNotificationObjectRequestDto('2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f')));

        self::assertSame(['yookassa', 'payment.succeeded', '2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f'], [$input->provider, $input->event, $input->objectId]);
    }

    public function testProviderBodyHydratesThroughTheSharedRequestMapper(): void
    {
        // Review #80 gate: the real request mapper must accept the full provider body and keep only the payment ID.
        $dto = ArrayToDtoMapper::map([
            'provider' => 'yookassa',
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => ['id' => '2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f', 'status' => 'succeeded', 'amount' => ['value' => '1.00', 'currency' => 'RUB'], 'paid' => true],
        ], PaymentNotificationRequestDto::class);

        self::assertSame('2d7f1f6c-000f-5000-9000-1a1b2c3d4e5f', new PaymentInputMapper()->notification($dto)->objectId);
        $empty = ArrayToDtoMapper::map(['provider' => 'yookassa', 'event' => 'payment.succeeded', 'object' => []], PaymentNotificationRequestDto::class);
        $this->expectExceptionMessage('INVALID_NOTIFICATION');
        new PaymentInputMapper()->notification($empty);
    }

    public function testNotificationWithoutPaymentIdIsRejected(): void
    {
        $this->expectExceptionMessage('INVALID_NOTIFICATION');
        new PaymentInputMapper()->notification(new PaymentNotificationRequestDto('yookassa', 'payment.succeeded', new PaymentNotificationObjectRequestDto()));
    }

    public function testStartValidatesTokenShapesBeforeTheUseCase(): void
    {
        $mapper = new PaymentInputMapper();
        $valid = $mapper->start(new StartPaymentRequestDto('3', str_repeat('a', 64), 'sbp', str_repeat('0', 32), '00000000-0000-4000-8000-000000000001'));
        self::assertSame('00000000-0000-4000-8000-000000000001', $valid->precedingAttemptId);

        $this->expectExceptionMessage('QUOTE_CHANGED');
        $mapper->start(new StartPaymentRequestDto('3', 'token', 'sbp', str_repeat('0', 32)));
    }
}
