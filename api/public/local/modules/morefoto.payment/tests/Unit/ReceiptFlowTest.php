<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Application\Payment\UseCase\ReconcilePaymentsUseCase;
use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptItem;
use Morefoto\Payment\Tests\Unit\Support\FakeOrders;
use Morefoto\Payment\Tests\Unit\Support\FakeProvider;
use Morefoto\Payment\Tests\Unit\Support\PaymentScenario;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G2-T04…T06, G2-T08: чек оплаты в попытке, его сверка и закрывающий чек печати после выдачи.
 *
 * @internal
 */
final class ReceiptFlowTest extends TestCase
{
    private const string ATTEMPT = '00000000-0000-4000-8000-000000000001';

    public function testAttemptFixesTheReceiptAndRepeatsItWithEveryCreation(): void
    {
        $scenario = new PaymentScenario(receiptsEnabled: true);
        $scenario->provider->answer(new ProviderUnavailableException('timeout'), FakeProvider::payment(self::ATTEMPT, 'pending'));
        $this->begin($scenario);
        $scenario->advance(60);
        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);

        self::assertCount(2, $scenario->provider->created);
        [$first, $second] = $scenario->provider->created;
        self::assertNotNull($first->receipt);
        self::assertEquals($first->receipt, $second->receipt, 'The repeat with the same key sends the same receipt.');
        self::assertSame('parent@example.test', $first->receipt->email);
        self::assertSame([
            ['Печать 10×15', 2, 30000, 'full_prepayment'],
            ['Электронная фотография', 1, 45000, 'full_payment'],
        ], array_map(static fn(ReceiptItem $item): array => [$item->description, $item->quantity, $item->amount, $item->mode->value], $first->receipt->items));
        self::assertNotNull($scenario->attempts->rows[1]['RECEIPT']);
    }

    public function testReceiptsOffKeepTheRequestOfG1(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(FakeProvider::payment(self::ATTEMPT, 'pending'), FakeProvider::payment(self::ATTEMPT, 'succeeded', paidAt: '2026-09-25 12:01:00'));
        $this->begin($scenario);
        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RETURN);

        self::assertNull($scenario->provider->created[0]->receipt);
        self::assertNull($scenario->attempts->rows[1]['RECEIPT']);
        self::assertSame('paid', $scenario->orders->order()->paymentStatus);
        self::assertSame([], $scenario->receipts->rows);
    }

    public function testConfirmedPaymentReceiptIsReconciledToItsRequisites(): void
    {
        $scenario = $this->paid();
        $receipt = $scenario->receipts->kind('payment');
        self::assertSame(['pending', 105000, 'yk-1', '2026-09-25 12:01:00'], [$receipt['STATUS'], $receipt['AMOUNT'], $receipt['PROVIDER_PAYMENT_ID'], $receipt['NEXT_CHECK_AT']]);

        $scenario->provider->answerReceipt([], [FakeProvider::receipt('succeeded')]);
        $scenario->receiptReconciler()->reconcile($receipt['ID']);
        self::assertSame('pending', $scenario->receipts->rows[$receipt['ID']]['STATUS'], 'No receipt at the provider yet.');
        $scenario->receiptReconciler()->reconcile($receipt['ID']);

        $receipt = $scenario->receipts->rows[$receipt['ID']];
        self::assertSame(['succeeded', 'rt-1', '3986', '9288000100115785', '2617603921', '2026-09-25 12:02:00', null, 2], [
            $receipt['STATUS'], $receipt['PROVIDER_RECEIPT_ID'], $receipt['FISCAL_DOCUMENT_NUMBER'], $receipt['FISCAL_STORAGE_NUMBER'],
            $receipt['FISCAL_ATTRIBUTE'], $receipt['REGISTERED_AT'], $receipt['NEXT_CHECK_AT'], $receipt['CHECK_COUNT'],
        ]);
        self::assertSame(['payment:yk-1', 'payment:yk-1'], $scenario->provider->receiptQueries);
    }

    public function testCanceledOrLateReceiptNeedsAManualCheckWithoutTouchingThePayment(): void
    {
        $canceled = $this->paid();
        $canceled->provider->answerReceipt([FakeProvider::receipt('canceled')]);
        $canceled->receiptReconciler()->reconcile(1);
        self::assertSame(['canceled', 'provider_canceled'], [$canceled->receipts->rows[1]['STATUS'], $canceled->receipts->rows[1]['ERROR']]);
        self::assertSame('paid', $canceled->orders->order()->paymentStatus);

        $late = $this->paid();
        $late->advance(72 * 3600);
        $late->provider->answerReceipt([]);
        $late->receiptReconciler()->reconcile(1);
        self::assertSame(['failed', 'timeout'], [$late->receipts->rows[1]['STATUS'], $late->receipts->rows[1]['ERROR']]);
    }

    public function testClosingReceiptWaitsForThePaymentReceiptThenSettlesThePrepayment(): void
    {
        $scenario = $this->paid();
        $payment = $scenario->receipts->kind('payment');
        self::assertTrue($scenario->registrar()->printHandedOver($payment, 5, $scenario->now));
        self::assertFalse($scenario->registrar()->printHandedOver($payment, 5, $scenario->now), 'A repeated handover adds no second closing receipt.');
        $closing = $scenario->receipts->kind('closing');
        self::assertSame(['queued', 60000, 5, '2026-09-25 12:00:00'], [$closing['STATUS'], $closing['AMOUNT'], $closing['HANDED_BY'], $closing['HANDED_AT']]);

        $scenario->receiptReconciler()->reconcile($closing['ID']);
        self::assertSame('queued', $scenario->receipts->rows[$closing['ID']]['STATUS'], 'The prepayment is not registered yet.');
        self::assertSame([], $scenario->provider->createdReceipts);

        $scenario->provider->answerReceipt([FakeProvider::receipt('succeeded')]);
        $scenario->receiptReconciler()->reconcile($payment['ID']);
        $scenario->provider->answerReceipt(new ProviderUnavailableException('timeout'), FakeProvider::receipt('pending', 'rt-2'), FakeProvider::receipt('succeeded', 'rt-2'));
        $scenario->receiptReconciler()->reconcile($closing['ID']);
        self::assertSame('pending', $scenario->receipts->rows[$closing['ID']]['STATUS'], 'An unknown outcome is retried with the same key.');
        $scenario->receiptReconciler()->reconcile($closing['ID']);
        $scenario->receiptReconciler()->reconcile($closing['ID']);

        self::assertCount(2, $scenario->provider->createdReceipts);
        [$first, $second] = $scenario->provider->createdReceipts;
        self::assertSame($first->idempotenceKey, $second->idempotenceKey);
        self::assertSame($closing['PROVIDER_KEY'], $first->idempotenceKey);
        self::assertSame(['yk-1', 60000, 'parent@example.test'], [$first->providerPaymentId, $first->prepayment, $first->receipt->email]);
        self::assertSame([['Печать 10×15', 2, 30000, 'full_payment']], array_map(static fn(ReceiptItem $item): array => [$item->description, $item->quantity, $item->amount, $item->mode->value], $first->receipt->items));
        self::assertSame(['succeeded', 'rt-2'], [$scenario->receipts->rows[$closing['ID']]['STATUS'], $scenario->receipts->rows[$closing['ID']]['PROVIDER_RECEIPT_ID']]);
        self::assertContains('rt-2', $scenario->provider->receiptQueries);
    }

    public function testClosingReceiptIsNeverRepeatedWithAnExpiredKeyOrAfterARefusal(): void
    {
        $expired = $this->handedOver();
        $expired->provider->answerReceipt(new ProviderUnavailableException('timeout'));
        $expired->receiptReconciler()->reconcile(2);
        $expired->advance(86400);
        $expired->receiptReconciler()->reconcile(2);
        self::assertSame(['failed', 'provider_key_expired'], [$expired->receipts->rows[2]['STATUS'], $expired->receipts->rows[2]['ERROR']]);
        self::assertCount(1, $expired->provider->createdReceipts);

        $refused = $this->handedOver();
        $refused->provider->answerReceipt(new ProviderRejectedException('HTTP 400'));
        $refused->receiptReconciler()->reconcile(2);
        self::assertSame(['failed', 'provider_rejected'], [$refused->receipts->rows[2]['STATUS'], $refused->receipts->rows[2]['ERROR']]);
    }

    public function testFailedPaymentReceiptFailsTheClosingOne(): void
    {
        $scenario = $this->paid();
        $scenario->registrar()->printHandedOver($scenario->receipts->kind('payment'), 5, $scenario->now);
        $scenario->provider->answerReceipt([FakeProvider::receipt('canceled')]);
        $scenario->receiptReconciler()->reconcile(1);
        $scenario->receiptReconciler()->reconcile(2);

        self::assertSame(['failed', 'payment_receipt_failed'], [$scenario->receipts->rows[2]['STATUS'], $scenario->receipts->rows[2]['ERROR']]);
        self::assertSame([], $scenario->provider->createdReceipts);
    }

    public function testScheduledReconciliationAlsoRunsDueReceipts(): void
    {
        $scenario = $this->paid();
        $reconcile = new ReconcilePaymentsUseCase($scenario->attempts, $scenario->reconciler(), $scenario->policy, $scenario->clock(), new NullLogger(), $scenario->receipts, $scenario->receiptReconciler());

        self::assertSame(0, $reconcile->execute(100)->receiptsChecked, 'The receipt check waits for its pause.');
        $scenario->advance(60);
        $scenario->provider->answerReceipt([FakeProvider::receipt('succeeded')]);
        $report = $reconcile->execute(100);

        self::assertSame([1, 0], [$report->receiptsChecked, $report->receiptsFailed]);
        self::assertSame('succeeded', $scenario->receipts->rows[1]['STATUS']);
    }

    private function paid(): PaymentScenario
    {
        return new PaymentScenario(receiptsEnabled: true)->paid(self::ATTEMPT, '2026-09-25 12:00:30');
    }

    /** Чек оплаты зарегистрирован, выдача отмечена: закрывающий чек — запись 2. */
    private function handedOver(): PaymentScenario
    {
        $scenario = $this->paid();
        $scenario->provider->answerReceipt([FakeProvider::receipt('succeeded')]);
        $scenario->receiptReconciler()->reconcile(1);
        $scenario->registrar()->printHandedOver($scenario->receipts->kind('payment'), 5, $scenario->now);

        return $scenario;
    }

    private function begin(PaymentScenario $scenario): void
    {
        $order = $scenario->orders->order();
        $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(str_repeat('ab', 16)), new StartPaymentInputDto($order->version, null, new PaymentQuoteToken()->token($order), 'sbp'));
    }
}
