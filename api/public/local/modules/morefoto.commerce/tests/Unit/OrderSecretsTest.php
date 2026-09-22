<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\IdempotencyKey;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderNumber;
use Morefoto\Commerce\Infrastructure\Order\CheckoutKeySeal;
use Morefoto\Commerce\Infrastructure\Order\OrderTokenGenerator;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderSecretsTest extends TestCase
{
    public function testSealedKeyOpensOnlyWithTheSameReplayKeyAndContext(): void
    {
        $seal = new CheckoutKeySeal();
        $key = (new OrderTokenGenerator())->secret();
        $sealed = $seal->seal($key, str_repeat('a', 32), 'gallery|1');

        self::assertSame(64, strlen($key));
        self::assertStringNotContainsString($key, $sealed);
        self::assertSame($key, $seal->open($sealed, str_repeat('a', 32), 'gallery|1'));
        foreach ([[str_repeat('b', 32), 'gallery|1'], [str_repeat('a', 32), 'gallery|2']] as [$replayKey, $context]) {
            try {
                $seal->open($sealed, $replayKey, $context);
                self::fail('Foreign replay key or context must not open the sealed order key.');
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testMalformedSealIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new CheckoutKeySeal())->open('bm90LWEtc2VhbA==', str_repeat('a', 32), 'gallery|1');
    }

    public function testKeyLivesThirtyCalendarDaysAndDatesUseMoscowDays(): void
    {
        $policy = new OrderCalendarPolicy();

        self::assertSame('2026-10-22 09:30:00', $policy->keyExpiresAt(new \DateTimeImmutable('2026-09-22T09:30:00Z'))->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-21 21:00:00', $policy->dayStart('2026-09-22')->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-22 21:00:00', $policy->dayStart('2026-09-22', true)->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-22T12:30:00+03:00', $policy->display(new \DateTimeImmutable('2026-09-22T09:30:00Z')));
        $this->expectException(\InvalidArgumentException::class);
        $policy->dayStart('2026-02-30');
    }

    public function testDisplayNumberAndReplayKeyFormats(): void
    {
        self::assertSame('MF-000042', (new OrderNumber(42))->value);
        self::assertSame('MF-1234567', (new OrderNumber(1234567))->value);
        self::assertSame(str_repeat('a', 32), (new IdempotencyKey(str_repeat('A', 32)))->value);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('INVALID_IDEMPOTENCY_KEY');
        new IdempotencyKey('not-a-key');
    }
}
