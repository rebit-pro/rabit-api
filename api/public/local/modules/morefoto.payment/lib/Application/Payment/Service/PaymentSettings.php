<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Service;

use Morefoto\Payment\Domain\Payment\Enum\PaymentMethodEnum;

/** Хранит серверное решение о приёме оплаты: включена ли она, какие способы показывать покупателю и куда
 * провайдер возвращает браузер. Без настроенного магазина оплата выключена, а не имитируется.
 */
final readonly class PaymentSettings
{
    /** @param list<PaymentMethodEnum> $methods */
    public function __construct(
        private bool $enabled,
        private array $methods,
        private string $returnBaseUrl,
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

    /** Адрес возврата без личного ключа заказа (G1-DEC-04). */
    public function returnUrl(string $attemptId): string
    {
        return rtrim($this->returnBaseUrl, '/') . '/orders/payment/' . $attemptId;
    }
}
