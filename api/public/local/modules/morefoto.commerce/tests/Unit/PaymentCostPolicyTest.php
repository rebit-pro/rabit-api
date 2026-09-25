<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\ValueObject\PaymentCostPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class PaymentCostPolicyTest extends TestCase
{
    #[DataProvider('examples')]
    public function testPublishesAgreedExamples(int $base, int $rateBps, bool $enabled, int $expected): void
    {
        self::assertSame($expected, (new PaymentCostPolicy($enabled, $rateBps))->salePrice($base));
    }

    /** @return iterable<string, array{int, int, bool, int}> */
    public static function examples(): iterable
    {
        yield '250 rub' => [25000, 380, true, 30000];
        yield '500 rub' => [50000, 380, true, 55000];
        yield '1000 rub' => [100000, 380, true, 105000];
        yield '2000 rub' => [200000, 380, true, 210000];
        yield 'exact compensation boundary' => [96200, 380, true, 100000];
        yield 'free gift stays free' => [0, 380, true, 0];
        yield 'zero rate still rounds up' => [50123, 0, true, 55000];
        yield 'disabled keeps base without rounding' => [50123, 380, false, 50123];
        yield 'maximum rate and price' => [ProductDetails::MAX_PRICE, PaymentCostPolicy::MAX_RATE_BPS, true, 111115000];
    }

    public function testSalePriceIsMinimalRoundedCompensationAcrossRange(): void
    {
        $checked = 0;
        foreach ([0, 1, 280, 350, 380, 999, PaymentCostPolicy::MAX_RATE_BPS] as $rate) {
            $policy = new PaymentCostPolicy(true, $rate);
            foreach ([...range(0, 200000, 137), 96199, 96200, 96201, ProductDetails::MAX_PRICE] as $base) {
                $price = $policy->salePrice($base);
                self::assertSame(0, $price % PaymentCostPolicy::ROUNDING_STEP);
                self::assertGreaterThanOrEqual($base * 10000, $price * (10000 - $rate), 'The rate is compensated.');
                if (0 < $price) {
                    self::assertLessThan($base * 10000, ($price - PaymentCostPolicy::ROUNDING_STEP) * (10000 - $rate), 'The step is minimal.');
                }
                ++$checked;
            }
        }
        self::assertGreaterThan(10000, $checked);
    }

    public function testHighestSalePriceFitsSignedInt(): void
    {
        $price = (new PaymentCostPolicy(true, PaymentCostPolicy::MAX_RATE_BPS))->salePrice(ProductDetails::MAX_PRICE);

        self::assertLessThanOrEqual(2147483647, $price);
    }

    public function testStaffHalfOfPublishedPriceIsNotRoundedAgain(): void
    {
        $price = (new PaymentCostPolicy(true, 380))->salePrice(50000);

        self::assertSame(27500, intdiv($price + 1, 2));
    }

    #[DataProvider('invalidRates')]
    public function testRejectsRateOutsideRange(int $rateBps): void
    {
        $this->expectException(InvalidConditionsException::class);
        new PaymentCostPolicy(true, $rateBps);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidRates(): iterable
    {
        yield 'negative' => [-1];
        yield 'above ten percent' => [PaymentCostPolicy::MAX_RATE_BPS + 1];
    }

    #[DataProvider('invalidPrices')]
    public function testRejectsBasePriceOutsideRange(int $base, bool $enabled): void
    {
        $this->expectException(InvalidConditionsException::class);
        (new PaymentCostPolicy($enabled, 380))->salePrice($base);
    }

    /** @return iterable<string, array{int, bool}> */
    public static function invalidPrices(): iterable
    {
        yield 'negative' => [-1, true];
        yield 'above cap when enabled' => [ProductDetails::MAX_PRICE + 1, true];
        yield 'above cap when disabled' => [ProductDetails::MAX_PRICE + 1, false];
    }
}
