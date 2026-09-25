<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Dto\StartPaymentInputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;
use Morefoto\Payment\Application\Payment\Service\PaymentQuoteToken;
use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;
use Morefoto\Payment\Domain\Payment\ValueObject\IdempotencyKey;
use Morefoto\Payment\Tests\Unit\Support\FakeOrders;
use Morefoto\Payment\Tests\Unit\Support\FakeProvider;
use Morefoto\Payment\Tests\Unit\Support\PaymentScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Payment\Domain\Payment\Enum\ConfirmationSourceEnum;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G1-T02, G1-T03, G1-T04, G1-T17: попытка создаётся одна, выбранным способом, без HTTP в транзакции.
 *
 * @internal
 */
final class StartPaymentAttemptTest extends TestCase
{
    private const string KEY_A = '0123456789abcdef0123456789abcdef';
    private const string KEY_B = 'fedcba9876543210fedcba9876543210';

    public function testNewAttemptCreatesProviderPaymentWithTheChosenMethod(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(FakeProvider::payment('00000000-0000-4000-8000-000000000001', 'pending'));

        $output = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $this->input($scenario, 'sbp'));

        self::assertTrue($output->created);
        self::assertSame('pending', $output->status);
        self::assertSame(105000, $output->amount);
        self::assertSame('https://yoomoney.ru/checkout/payments/v2/contract?orderId=yk-1', $output->redirectUrl);
        self::assertSame('2', $output->orderVersion, 'Order became pending and its version grew.');
        $created = $scenario->provider->created[0];
        self::assertSame('sbp', $created->paymentMethod);
        self::assertSame('https://app.example.test/orders/payment/00000000-0000-4000-8000-000000000001', $created->returnUrl);
        self::assertStringNotContainsString(FakeOrders::KEY, $created->returnUrl, 'The personal order key never reaches the provider.');
        self::assertSame('00000000-0000-4000-8000-000000000002', $created->idempotenceKey);
        self::assertSame('pending', $scenario->orders->order()->paymentStatus);
        self::assertSame('yk-1', $scenario->attempts->rows[1]['PROVIDER_PAYMENT_ID']);
    }

    public function testRepeatDoubleClickAndSecondTabGetTheSameAttempt(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(FakeProvider::payment('00000000-0000-4000-8000-000000000001', 'pending'));
        $input = $this->input($scenario, 'bank_card');
        $first = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $input);

        $replay = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $input);
        $secondTab = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_B), $input);

        self::assertSame($first->id, $replay->id);
        self::assertSame($first->id, $secondTab->id);
        self::assertFalse($replay->created);
        self::assertFalse($secondTab->created);
        self::assertCount(1, $scenario->provider->created, 'Only one provider payment exists.');
        self::assertCount(1, $scenario->attempts->rows);
    }

    public function testSameKeyWithAnotherBodyIsAConflict(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(FakeProvider::payment('00000000-0000-4000-8000-000000000001', 'pending'));
        $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $this->input($scenario, 'sbp'));

        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $this->input($scenario, 'bank_card', '1'));
    }

    public function testProviderTimeoutLeavesAnUnknownAttemptThatBlocksANewOne(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(new ProviderUnavailableException('timeout'));

        $output = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $this->input($scenario, 'sbp'));

        self::assertSame('unknown', $output->status);
        self::assertNull($output->redirectUrl);
        self::assertSame('2026-09-25 12:01:00', $scenario->attempts->rows[1]['NEXT_CHECK_AT']);
        $quote = $scenario->quote()->execute(FakeOrders::KEY);
        self::assertFalse($quote->canPay);
        self::assertSame($output->id, $quote->activeAttemptId);
        // The retry after the pause reuses the stored provider key: no second payment.
        $scenario->provider->answer(FakeProvider::payment($output->id, 'pending'));
        $scenario->advance(61);
        $scenario->reconciler()->reconcile(1, ConfirmationSourceEnum::RECONCILE);
        self::assertSame($scenario->provider->created[0]->idempotenceKey, $scenario->provider->created[1]->idempotenceKey);
        self::assertSame('pending', $scenario->attempts->rows[1]['STATUS']);
    }

    public function testRejectedCreationDeclinesTheOrderAndAllowsANextAttempt(): void
    {
        $scenario = new PaymentScenario();
        $scenario->provider->answer(new ProviderRejectedException('HTTP 400'));
        $first = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $this->input($scenario, 'sbp'));
        self::assertSame('canceled', $first->status);
        self::assertSame('declined', $scenario->orders->order()->paymentStatus);

        $quote = $scenario->quote()->execute(FakeOrders::KEY);
        self::assertTrue($quote->canPay);
        self::assertSame($first->id, $quote->precedingAttemptId);
        $scenario->provider->answer(FakeProvider::payment('00000000-0000-4000-8000-000000000003', 'pending', id: 'yk-2'));
        $second = $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_B), new StartPaymentInputDto($quote->orderVersion, $first->id, $quote->quoteToken, 'sbp'));

        self::assertTrue($second->created);
        self::assertSame(1, $scenario->attempts->rows[2]['PRECEDING_ID']);
    }

    #[DataProvider('refusals')]
    public function testRefusals(callable $arrange, string $code): void
    {
        $scenario = new PaymentScenario();
        $input = $arrange($scenario) ?? $this->input($scenario, 'sbp');

        try {
            $scenario->start()->execute(FakeOrders::KEY, new IdempotencyKey(self::KEY_A), $input);
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
        }
        self::assertSame([], $scenario->provider->created, 'A refused start never calls the provider.');
    }

    public static function refusals(): iterable
    {
        yield 'payment disabled' => [static function(PaymentScenario $scenario): null {
            $scenario->enabled = false;

            return null;
        }, 'PAYMENT_DISABLED'];
        yield 'method not configured' => [static function(PaymentScenario $scenario): null {
            $scenario->methods = [PaymentMethodEnum::BANK_CARD];

            return null;
        }, 'PAYMENT_METHOD_UNAVAILABLE'];
        yield 'stale version' => [static fn(PaymentScenario $scenario): StartPaymentInputDto => new StartPaymentInputDto('9', null, new PaymentQuoteToken()->token($scenario->orders->order()), 'sbp'), 'QUOTE_CHANGED'];
        yield 'stale quote token' => [static fn(PaymentScenario $scenario): StartPaymentInputDto => new StartPaymentInputDto('1', null, str_repeat('0', 64), 'sbp'), 'QUOTE_CHANGED'];
        yield 'foreign preceding attempt' => [static fn(PaymentScenario $scenario): StartPaymentInputDto => new StartPaymentInputDto('1', '00000000-0000-4000-8000-000000000099', new PaymentQuoteToken()->token($scenario->orders->order()), 'sbp'), 'ATTEMPT_CONFLICT'];
        yield 'zero total' => [static function(PaymentScenario $scenario): null {
            $scenario->orders->total = 0;

            return null;
        }, 'NOTHING_TO_PAY'];
        yield 'closed period' => [static function(PaymentScenario $scenario): null {
            $scenario->orders->closesAt = '2026-09-25 11:59:59';

            return null;
        }, 'PAYMENT_CLOSED'];
    }

    public function testZeroTotalIsNeverOfferedForPayment(): void
    {
        $scenario = new PaymentScenario(orders: new FakeOrders(total: 0));

        $quote = $scenario->quote()->execute(FakeOrders::KEY);

        self::assertFalse($quote->canPay);
        self::assertSame(0, $quote->total);
    }

    public function testUnknownOrderKeyIsIndistinguishable(): void
    {
        $this->expectExceptionMessage('ORDER_NOT_FOUND');
        $scenario = new PaymentScenario();
        $scenario->start()->execute(str_repeat('b', 64), new IdempotencyKey(self::KEY_A), $this->input($scenario, 'sbp'));
    }

    private function input(PaymentScenario $scenario, string $method, ?string $version = null): StartPaymentInputDto
    {
        $order = $scenario->orders->order();

        return new StartPaymentInputDto($version ?? $order->version, null, new PaymentQuoteToken()->token($order), $method);
    }
}
