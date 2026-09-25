<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Morefoto\Payment\Domain\Payment\Service\PaymentAttemptPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class PaymentAttemptPolicyTest extends TestCase
{
    private const string CLOSES_AT = '2026-09-30 21:00:00';

    #[DataProvider('refusals')]
    public function testStartIsRefusedForPaidZeroAndClosed(string $status, string $now, string $code, int $amount = 105000): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);

        new PaymentAttemptPolicy()->assertCanStart($status, $amount, self::CLOSES_AT, new \DateTimeImmutable($now, new \DateTimeZone('UTC')));
    }

    public static function refusals(): iterable
    {
        yield 'paid order' => ['paid', '2026-09-25 12:00:00', 'ORDER_ALREADY_PAID'];
        yield 'exactly at close' => ['unpaid', self::CLOSES_AT, 'PAYMENT_CLOSED'];
        yield 'after close' => ['declined', '2026-10-01 00:00:00', 'PAYMENT_CLOSED'];
        yield 'nothing to pay' => ['unpaid', '2026-09-25 12:00:00', 'NOTHING_TO_PAY', 0];
    }

    public function testStartIsAllowedBeforeCloseAndWithoutDeadline(): void
    {
        $policy = new PaymentAttemptPolicy();
        $now = new \DateTimeImmutable('2026-09-30 20:59:59', new \DateTimeZone('UTC'));

        $policy->assertCanStart('declined', 1, self::CLOSES_AT, $now);
        self::assertTrue($policy->canStart('unpaid', 1, null, $now, false));
        self::assertFalse($policy->canStart('unpaid', 0, null, $now, false), 'A zero total is not paid.');
        self::assertFalse($policy->canStart('unpaid', 1, self::CLOSES_AT, $now, true), 'An open attempt blocks a new one.');
        // A moment in another zone is compared in UTC.
        self::assertFalse($policy->canStart('unpaid', 1, self::CLOSES_AT, new \DateTimeImmutable('2026-10-01 00:00:00', new \DateTimeZone('Europe/Moscow')), false));
    }

    public function testLatePaymentIsAConfirmationAtOrAfterClose(): void
    {
        $policy = new PaymentAttemptPolicy();

        self::assertFalse($policy->isLate(self::CLOSES_AT, '2026-09-30 20:59:59'));
        self::assertTrue($policy->isLate(self::CLOSES_AT, self::CLOSES_AT));
        self::assertFalse($policy->isLate(null, '2026-12-31 00:00:00'));
    }

    public function testProviderAnswerMustBelongToTheAttempt(): void
    {
        $policy = new PaymentAttemptPolicy();
        $check = static fn(int $amount = 105000, string $currency = 'RUB', string $shop = '123456', ?string $attempt = 'a-1'): ?string => $policy->mismatch(105000, '123456', 'a-1', $amount, $currency, $shop, $attempt);

        self::assertNull($check());
        self::assertSame('provider_amount_mismatch', $check(amount: 104999));
        self::assertSame('provider_amount_mismatch', $check(currency: 'USD'));
        self::assertSame('provider_shop_mismatch', $check(shop: '654321'));
        self::assertSame('provider_attempt_mismatch', $check(attempt: null));
    }

    public function testChecksSlowDownAndStopAtAFinalStatus(): void
    {
        $policy = new PaymentAttemptPolicy();
        $now = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));

        self::assertSame(['2026-09-25 12:01:00', '2026-09-25 12:02:00', '2026-09-25 12:05:00', '2026-09-25 12:15:00', '2026-09-25 13:00:00', '2026-09-25 13:00:00'], array_map(
            static fn(int $count): ?string => $policy->nextCheckAt(AttemptStatusEnum::PENDING, $count, $now),
            [1, 2, 3, 4, 5, 40],
        ));
        self::assertNull($policy->nextCheckAt(AttemptStatusEnum::SUCCEEDED, 1, $now));
        self::assertNull($policy->nextCheckAt(AttemptStatusEnum::CANCELED, 1, $now));
    }

    public function testProviderKeyIsReusableOnlyWithinTheGuarantee(): void
    {
        $policy = new PaymentAttemptPolicy();
        $now = new \DateTimeImmutable('2026-09-26 12:00:00', new \DateTimeZone('UTC'));

        self::assertTrue($policy->providerKeyUsable('2026-09-25 12:00:01', $now));
        self::assertFalse($policy->providerKeyUsable('2026-09-25 12:00:00', $now));
    }
}
