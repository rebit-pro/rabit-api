<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Service;

use Morefoto\Payment\Domain\Payment\Enum\AttemptStatusEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Предметные правила оплаты заказа: когда можно начать попытку, какой платёж поздний, когда ответ провайдера
 * не принадлежит попытке и как часто перепроверять незавершённый исход. Моменты — UTC `Y-m-d H:i:s`.
 */
final readonly class PaymentAttemptPolicy
{
    /** Паузы сверки после 1-й…4-й проверки, дальше — раз в час до конечного статуса. */
    private const array CHECK_DELAYS = [60, 120, 300, 900];
    private const int HOURLY_DELAY = 3600;
    /** Срок, в течение которого провайдер гарантирует тот же ответ на повтор с тем же ключом идемпотентности. */
    private const int PROVIDER_KEY_TTL = 86400;

    /**
     * Новая попытка — только по неоплаченному заказу с суммой больше нуля и до срока продаж его состава (G1-DEC-02, E7):
     * заказ с печатью после закрытия печати — PRINT_SALES_CLOSED, остальной после окончания продаж — PAYMENT_CLOSED.
     */
    public function assertCanStart(string $orderPaymentStatus, int $amount, ?string $salesUntil, bool $containsPrint, \DateTimeImmutable $now): void
    {
        if ('paid' === $orderPaymentStatus) {
            throw new HttpException('ORDER_ALREADY_PAID', 409);
        }
        if (0 >= $amount) {
            throw new HttpException('NOTHING_TO_PAY', 409);
        }
        if (null !== $salesUntil && $this->utc($now) >= $salesUntil) {
            throw new HttpException($containsPrint ? 'PRINT_SALES_CLOSED' : 'PAYMENT_CLOSED', 409);
        }
    }

    public function canStart(string $orderPaymentStatus, int $amount, ?string $salesUntil, \DateTimeImmutable $now, bool $hasOpenAttempt): bool
    {
        return !$hasOpenAttempt && 'paid' !== $orderPaymentStatus && 0 < $amount && (null === $salesUntil || $this->utc($now) < $salesUntil);
    }

    /**
     * Оплата, подтверждённая после срока продаж состава заказа, — денежный факт с latePayment; исполнение решает I3
     * (G1-D12-SCOPE). Электронный заказ, оплаченный между закрытием печати и окончанием продаж электронных, не поздний.
     */
    public function isLate(?string $salesUntil, string $paidAt): bool
    {
        return null !== $salesUntil && $paidAt >= $salesUntil;
    }

    /**
     * Причина, по которой платёж провайдера нельзя принять за эту попытку; null — платёж принадлежит ей.
     */
    public function mismatch(
        int $expectedAmount,
        string $expectedShopId,
        string $expectedAttemptId,
        int $amount,
        string $currency,
        string $shopId,
        ?string $attemptId,
    ): ?string {
        return match (true) {
            $attemptId !== $expectedAttemptId => 'provider_attempt_mismatch',
            $shopId !== $expectedShopId => 'provider_shop_mismatch',
            'RUB' !== $currency || $amount !== $expectedAmount => 'provider_amount_mismatch',
            default => null,
        };
    }

    public function nextCheckAt(AttemptStatusEnum $status, int $checkCount, \DateTimeImmutable $now): ?string
    {
        if (!$status->isOpen()) {
            return null;
        }
        $delay = self::CHECK_DELAYS[$checkCount - 1] ?? self::HOURLY_DELAY;

        return $this->utc($now->modify('+' . $delay . ' seconds'));
    }

    /** После срока гарантии повтор создания может создать второй платёж, поэтому попытка остаётся unknown для ручного решения. */
    public function providerKeyUsable(string $createdAt, \DateTimeImmutable $now): bool
    {
        return $this->utc($now->modify('-' . self::PROVIDER_KEY_TTL . ' seconds')) < $createdAt;
    }

    public function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
