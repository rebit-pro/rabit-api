<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\UseCase;

use Morefoto\Payment\Application\Receipt\Dto\OrderReceiptsOutputDto;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptOutputMapper;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;

/** Показывает покупателю по личному ключу заказа его кассовые чеки (PAY-04, G2-DEC-06): чек оплаты и закрывающий чек
 * печати с состоянием и реквизитами для проверки в приложении ФНС. Сам чек ЮKassa присылает на email заказа.
 */
final readonly class GetOrderReceiptsUseCase
{
    private const string EMAIL = 'email';

    public function __construct(
        private OrderPaymentInterface $orders,
        private PaymentReceiptRepositoryInterface $receipts,
        private ReceiptSettings $settings,
        private ReceiptOutputMapper $mapper,
    ) {}

    public function execute(?string $orderKey): OrderReceiptsOutputDto
    {
        $order = $this->orders->byKey($orderKey);
        $receipts = array_map($this->mapper->receipt(...), $this->receipts->forOrder($order->id));

        return new OrderReceiptsOutputDto(
            enabled: $this->settings->enabled(),
            channel: [] !== $receipts || $this->settings->enabled() ? self::EMAIL : null,
            receipts: $receipts,
        );
    }
}
