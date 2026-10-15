<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

/** Хранит серверное решение о включении оформления, письма со ссылкой на заказ (J2) и список реально подключённых каналов чека.
 * Оформление включается только флагом окружения изолированного стенда; до фискализации G2 каналов чека нет. Письмо
 * включается отдельным флагом после проверки реальной доставки (J2-DEC-05).
 */
final readonly class CheckoutAvailability
{
    public function __construct(private bool $enabled, private bool $orderEmail = false) {}

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
        return [];
    }
}
