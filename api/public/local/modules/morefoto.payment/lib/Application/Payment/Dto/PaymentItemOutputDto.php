<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentItemOutputDto
{
    /** @param string $createdAt бизнес-время Europe/Moscow ATOM */
    public function __construct(
        public string $id,
        public string $orderId,
        public string $orderNumber,
        public string $institutionName,
        public string $groupName,
        public string $createdAt,
        public int $amount,
        public string $currency,
        public string $paymentMethod,
        public string $status,
        public ?string $paidAt,
        public bool $latePayment,
        public ?int $incomeAmount,
        public ?string $cancelReason,
    ) {}
}
