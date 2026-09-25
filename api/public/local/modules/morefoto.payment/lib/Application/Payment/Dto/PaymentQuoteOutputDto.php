<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentQuoteOutputDto
{
    /**
     * @param null|string  $precedingAttemptId последняя попытка заказа — её ID передаётся в новую попытку
     * @param null|string  $activeAttemptId    открытая попытка, результат которой нужно дождаться
     * @param list<string> $paymentMethods     способы оплаты магазина в порядке показа
     */
    public function __construct(
        public int $subtotal,
        public int $discount,
        public int $giftSaving,
        public int $total,
        public string $quoteToken,
        public string $orderVersion,
        public bool $canPay,
        public ?string $precedingAttemptId,
        public ?string $activeAttemptId,
        public array $paymentMethods,
    ) {}
}
