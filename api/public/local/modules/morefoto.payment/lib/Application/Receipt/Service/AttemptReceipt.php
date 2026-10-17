<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Service;

use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptComposer;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptLine;
use Rebit\Share\Contracts\Commerce\OrderReceiptSourceInterface;

/** Фиксирует чек оплаты при старте попытки (G2-DEC-04): позиции из неизменяемого снимка заказа по текущим налоговым
 * настройкам. Тот же снимок уходит в каждый повтор создания платежа, поэтому смена настроек не меняет начатые попытки.
 */
final readonly class AttemptReceipt
{
    public function __construct(
        private ReceiptSettings $settings,
        private OrderReceiptSourceInterface $orders,
        private ReceiptComposer $composer,
        private ReceiptSnapshotMapper $snapshots,
    ) {}

    /** @return null|string снимок чека для попытки; null — чеки выключены */
    public function snapshot(int $orderId, int $total): ?string
    {
        if (!$this->settings->enabled()) {
            return null;
        }
        $order = $this->orders->find($orderId);
        $lines = [];
        foreach ($order->lines as $line) {
            $lines[] = new ReceiptLine('physical' === $line->kind, $line->name, $line->quantity, $line->unitPrice, $line->total);
        }

        return $this->snapshots->encode($this->composer->payment($order->email, $lines, $total, $this->settings->rules()));
    }
}
