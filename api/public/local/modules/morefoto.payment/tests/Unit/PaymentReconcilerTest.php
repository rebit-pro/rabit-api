<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Dto\PaymentNotificationInputDto;
use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\UseCase\AcceptPaymentNotificationUseCase;
use Morefoto\Payment\Application\Payment\UseCase\ReconcilePaymentsUseCase;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\Repository\PaymentNotificationRepositoryInterface;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Morefoto\Payment\Tests\Unit\Support\FakeOrders;
use Morefoto\Payment\Tests\Unit\Support\FakeProvider;
use Morefoto\Payment\Tests\Unit\Support\PaymentScenario;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G1-T05…T08, G1-T11: сверка, денежный факт, поздний платёж, уведомление и фоновая проверка.
 *
 * @internal
 */
final class PaymentReconcilerTest extends TestCase
{
    private const string ATTEMPT = '00000000-0000-4000-8000-000000000001';

    public function testSucceededPaymentWritesOneFactAndPaysTheOrder(): void
    {
        $scenario = $this->pending();
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:03:00'));

        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RETURN);
        // A repeated signal after the final status neither asks the provider nor writes money again.
        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::NOTIFICATION);

        self::assertCount(1, $scenario->provider->found);
        self::assertCount(1, $scenario->facts->rows);
        $fact = $scenario->facts->rows['yookassa:yk-1'];
        self::assertSame(105000, $fact['AMOUNT']);
        self::assertSame(101010, $fact['INCOME_AMOUNT']);
        self::assertSame('return', $fact['CONFIRMED_BY']);
        self::assertFalse($fact['LATE_PAYMENT']);
        $order = $scenario->orders->order();
        self::assertSame('paid', $order->paymentStatus);
        self::assertSame('2026-09-25 12:03:00', $order->paidAt);
        self::assertNull($scenario->attempts->rows[1]['NEXT_CHECK_AT']);
    }

    public function testPaymentConfirmedAfterCloseIsLateButStillAMoneyFact(): void
    {
        $scenario = $this->pending(new FakeOrders(closesAt: '2026-09-25 12:02:00'));
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:02:30'));

        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);

        self::assertTrue($scenario->facts->rows['yookassa:yk-1']['LATE_PAYMENT']);
        self::assertTrue($scenario->orders->order()->latePayment);
        self::assertSame('paid', $scenario->orders->order()->paymentStatus);
    }

    public function testForeignAmountShopOrAttemptNeverPaysTheOrder(): void
    {
        foreach ([
            FakeProvider::payment(self::ATTEMPT, 'succeeded', amount: 100, paidAt: '2026-09-25 12:03:00'),
            FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:03:00', shopId: '999'),
            FakeProvider::payment('00000000-0000-4000-8000-000000000042', 'succeeded', paidAt: '2026-09-25 12:03:00'),
            FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:03:00', id: 'yk-other'),
        ] as $foreign) {
            $scenario = $this->pending();
            $scenario->provider->answer($foreign);

            $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::NOTIFICATION);

            self::assertSame([], $scenario->facts->rows);
            self::assertSame('pending', $scenario->orders->order()->paymentStatus);
            $attempt = $scenario->attempts->rows[1];
            self::assertSame('unknown', $attempt['STATUS'], 'The attempt stays blocked for a manual decision.');
            self::assertNull($attempt['NEXT_CHECK_AT']);
            self::assertSame('yk-1', $attempt['PROVIDER_PAYMENT_ID']);
            self::assertStringStartsWith('provider_', (string)$attempt['CANCEL_REASON']);
        }
    }

    public function testCanceledPaymentDeclinesTheOrder(): void
    {
        $scenario = $this->pending();
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'canceled'));

        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RETURN);

        self::assertSame('canceled', $scenario->attempts->rows[1]['STATUS']);
        self::assertSame('expired_on_confirmation', $scenario->attempts->rows[1]['CANCEL_REASON']);
        self::assertSame('declined', $scenario->orders->order()->paymentStatus);
    }

    public function testUnavailableProviderKeepsThePendingAttemptAndSlowsDown(): void
    {
        $scenario = $this->pending();
        $scenario->provider->answer(new ProviderUnavailableException('HTTP 500'), new ProviderUnavailableException('HTTP 503'));

        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);
        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);

        $attempt = $scenario->attempts->rows[1];
        self::assertSame('pending', $attempt['STATUS']);
        self::assertSame(3, $attempt['CHECK_COUNT']);
        self::assertSame('2026-09-25 12:05:00', $attempt['NEXT_CHECK_AT']);
        self::assertSame('pending', $scenario->orders->order()->paymentStatus);
    }

    public function testUnknownCreationBeyondTheKeyGuaranteeIsNotRetriedBlindly(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(new ProviderUnavailableException('timeout'));
        $this->begin($scenario);
        $scenario->advance(86400);

        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);

        self::assertCount(1, $scenario->provider->created, 'No second creation after the provider guarantee.');
        self::assertSame('unknown', $scenario->attempts->rows[1]['STATUS']);
        self::assertSame('provider_key_expired', $scenario->attempts->rows[1]['CANCEL_REASON']);
        self::assertNull($scenario->attempts->rows[1]['NEXT_CHECK_AT']);
    }

    public function testNotificationIsASignalAndItsRepeatChangesNothing(): void
    {
        $scenario = $this->pending();
        $notifications = new class implements PaymentNotificationRepositoryInterface {
            /** @var array<string, null|string> */
            public array $rows = [];

            public function register(string $provider, string $eventKey, string $event, string $objectId, string $receivedAt): bool
            {
                $this->rows[$eventKey] ??= null;

                return null !== $this->rows[$eventKey];
            }

            public function complete(string $provider, string $eventKey, string $result, string $processedAt): void
            {
                $this->rows[$eventKey] = $result;
            }
        };
        $accept = new AcceptPaymentNotificationUseCase($scenario->provider, $scenario->attempts, $notifications, $scenario->reconciler(), $scenario->policy, $scenario->clock());
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:03:00'));

        $accept->execute(new PaymentNotificationInputDto('yookassa', 'payment.succeeded', 'yk-1'));
        $accept->execute(new PaymentNotificationInputDto('yookassa', 'payment.succeeded', 'yk-1'));
        $accept->execute(new PaymentNotificationInputDto('yookassa', 'payment.succeeded', 'yk-foreign'));
        $accept->execute(new PaymentNotificationInputDto('yookassa', 'refund.succeeded', 'rf-1'));

        self::assertCount(1, $scenario->provider->found, 'The body is never trusted: one provider check, no repeat.');
        self::assertSame(['payment.succeeded:yk-1' => 'succeeded', 'payment.succeeded:yk-foreign' => 'unknown_payment'], $notifications->rows);
        self::assertSame('paid', $scenario->orders->order()->paymentStatus);
        self::assertSame('notification', $scenario->facts->rows['yookassa:yk-1']['CONFIRMED_BY']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('PROVIDER_NOT_FOUND');
        $accept->execute(new PaymentNotificationInputDto('sberpay', 'payment.succeeded', 'yk-1'));
    }

    public function testScheduledReconciliationPicksOnlyDueAttempts(): void
    {
        $scenario = $this->pending();
        $reconcile = new ReconcilePaymentsUseCase($scenario->attempts, $scenario->reconciler(), $scenario->policy, $scenario->clock(), new NullLogger());

        self::assertSame(0, $reconcile->execute(100)->checked, 'The first check waits for its pause.');
        $scenario->advance(60);
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:01:00'));
        $report = $reconcile->execute(100);

        self::assertSame(1, $report->checked);
        self::assertSame(0, $report->failed);
        self::assertSame('reconcile', $scenario->facts->rows['yookassa:yk-1']['CONFIRMED_BY']);
        self::assertSame(0, $reconcile->execute(100)->checked, 'A final attempt is no longer due.');
    }

    public function testReturnPageReconcilesAtMostEveryFiveSeconds(): void
    {
        $scenario = $this->pending();
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'pending'), FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:00:06'));

        self::assertSame('pending', $scenario->attempt()->execute(FakeOrders::KEY, self::ATTEMPT)->status, 'The start check was just made.');
        $scenario->advance(5);
        self::assertSame('pending', $scenario->attempt()->execute(FakeOrders::KEY, self::ATTEMPT)->status);
        self::assertSame('pending', $scenario->attempt()->execute(FakeOrders::KEY, self::ATTEMPT)->status, 'Throttled: no provider call.');
        $scenario->advance(5);
        $paid = $scenario->attempt()->execute(FakeOrders::KEY, self::ATTEMPT);

        self::assertSame('succeeded', $paid->status);
        self::assertNull($paid->redirectUrl);
        self::assertCount(2, $scenario->provider->found);
    }

    public function testForeignAttemptIsNotFound(): void
    {
        $scenario = $this->pending();

        $this->expectExceptionMessage('PAYMENT_ATTEMPT_NOT_FOUND');
        $scenario->attempt()->execute(FakeOrders::KEY, '00000000-0000-4000-8000-000000000042');
    }

    private function pending(?FakeOrders $orders = null): PaymentScenario
    {
        $scenario = new PaymentScenario(orders: $orders);
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'pending'));
        $this->begin($scenario);

        return $scenario;
    }

    private function begin(PaymentScenario $scenario): void
    {
        $order = $scenario->orders->order();
        $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(str_repeat('ab', 16)), new StartPaymentInputDto($order->version, null, new PaymentQuoteToken()->token($order), 'sbp'));
    }
}
