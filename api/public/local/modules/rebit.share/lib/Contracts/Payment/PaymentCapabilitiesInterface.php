<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Payment;

/** Что оплата сообщает оформлению заказа: реально подключённые каналы кассового чека и тестовый ли магазин (G2). */
interface PaymentCapabilitiesInterface
{
    /** @return list<string> каналы чека; email — когда чеки ЮKassa включены и настроены */
    public function receiptChannels(): array;

    /** Подключён тестовый магазин провайдера: деньги не списываются (issue #120). */
    public function testMode(): bool;
}
