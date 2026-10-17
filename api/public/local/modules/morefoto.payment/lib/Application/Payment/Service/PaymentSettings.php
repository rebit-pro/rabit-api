<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Service;

use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;

/** Хранит серверное решение о приёме оплаты: включена ли она, какие способы показывать покупателю, куда провайдер
 * возвращает браузер и тестовый ли магазин (issue #120). Без настроенного магазина оплата выключена, а не имитируется.
 */
final readonly class PaymentSettings
{
    /** @param list<PaymentMethodEnum> $methods */
    public function __construct(
        private bool $enabled,
        private array $methods,
        private string $returnBaseUrl,
        private bool $testMode = false,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled && [] !== $this->methods;
    }

    /** @return list<PaymentMethodEnum> */
    public function methods(): array
    {
        return $this->enabled ? $this->methods : [];
    }

    /** Подключён тестовый магазин провайдера: деньги не списываются. */
    public function testMode(): bool
    {
        return $this->enabled() && $this->testMode;
    }

    /** Адрес возврата без личного ключа заказа (G1-DEC-04). */
    public function returnUrl(string $attemptId): string
    {
        return rtrim($this->returnBaseUrl, '/') . '/orders/payment/' . $attemptId;
    }
}
