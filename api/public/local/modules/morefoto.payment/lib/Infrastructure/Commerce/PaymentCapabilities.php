<?php

declare(strict_types=1);

namespace Morefoto\Payment\Infrastructure\Commerce;

use Morefoto\Payment\Application\Payment\Service\PaymentSettings;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Rebit\Share\Contracts\Payment\PaymentCapabilitiesInterface;

/** Сообщает оформлению Commerce о чеках и тестовом магазине из настроек оплаты; окружение Payment Commerce не читает. */
final readonly class PaymentCapabilities implements PaymentCapabilitiesInterface
{
    private const string EMAIL = 'email';

    public function __construct(
        private PaymentSettings $payment,
        private ReceiptSettings $receipts,
    ) {}

    public function receiptChannels(): array
    {
        // «Чеки от ЮKassa» доставляют чек только на email заказа.
        return $this->payment->enabled() && $this->receipts->enabled() ? [self::EMAIL] : [];
    }

    public function testMode(): bool
    {
        return $this->payment->testMode();
    }
}
