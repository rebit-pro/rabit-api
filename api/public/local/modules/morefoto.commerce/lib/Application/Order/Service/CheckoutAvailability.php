<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

/** Хранит серверное решение о включении оформления и список реально подключённых каналов чека.
 * Оформление включается только флагом окружения изолированного стенда; до фискализации G2 каналов чека нет.
 */
final readonly class CheckoutAvailability
{
    public function __construct(private bool $enabled) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** @return list<string> */
    public function receiptChannels(): array
    {
        return [];
    }
}
