<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce\Dto;

final readonly class OrderPaymentInputDto
{
    /**
     * @param string      $status pending, declined или paid
     * @param null|string $paidAt момент подтверждённой оплаты UTC `Y-m-d H:i:s`, только для paid
     */
    public function __construct(
        public int $orderId,
        public string $status,
        public ?string $paidAt = null,
        public bool $latePayment = false,
    ) {}
}
