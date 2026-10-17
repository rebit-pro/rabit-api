<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Rebit\Share\Contracts\Payment\PaymentCapabilitiesInterface;

/** Хранит серверное решение о включении оформления, письма со ссылкой на заказ (J2) и список реально подключённых каналов чека.
 * Оформление включается только флагом окружения изолированного стенда; каналы чека и тестовый режим магазина сообщает
 * оплата (G2). Письмо включается отдельным флагом после проверки реальной доставки (J2-DEC-05).
 */
final readonly class CheckoutAvailability
{
    public function __construct(
        private bool $enabled,
        private bool $orderEmail = false,
        private ?PaymentCapabilitiesInterface $payment = null,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function orderEmail(): bool
    {
        return $this->orderEmail;
    }

    /** @return list<string> */
    public function receiptChannels(): array
    {
        return $this->payment?->receiptChannels() ?? [];
    }

    /** Тестовый магазин оплаты: покупатель видит, что деньги не списываются (issue #120). */
    public function paymentTestMode(): bool
    {
        return $this->payment?->testMode() ?? false;
    }
}
