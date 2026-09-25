<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\ValueObject;

use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;

/**
 * Политика учёта расходов на оплату в цене продажи (E6). Включённая политика поднимает цену единицы каталога так,
 * чтобы после удержания ставки с полученной суммы осталась базовая цена, и округляет результат вверх до 50 ₽;
 * выключенная возвращает базовую цену без округления.
 */
final readonly class PaymentCostPolicy
{
    /** Шаг округления цены продажи в копейках (50 ₽). */
    public const int ROUNDING_STEP = 5000;
    /** Верхний предел ставки в базисных пунктах (10%, решение E6-DEC-01). */
    public const int MAX_RATE_BPS = 1000;
    private const int FULL_RATE_BPS = 10000;

    public function __construct(
        public bool $enabled,
        public int $rateBps,
    ) {
        if (0 > $rateBps || self::MAX_RATE_BPS < $rateBps) {
            throw new InvalidConditionsException('Payment cost rate is outside the supported range.');
        }
    }

    public function salePrice(int $basePrice): int
    {
        if (0 > $basePrice || ProductDetails::MAX_PRICE < $basePrice) {
            throw new InvalidConditionsException('Product price is outside the supported range.');
        }
        if (!$this->enabled) {
            return $basePrice;
        }
        $divisor = (self::FULL_RATE_BPS - $this->rateBps) * self::ROUNDING_STEP;

        return intdiv($basePrice * self::FULL_RATE_BPS + $divisor - 1, $divisor) * self::ROUNDING_STEP;
    }
}
