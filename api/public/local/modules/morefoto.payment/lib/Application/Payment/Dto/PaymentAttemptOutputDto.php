<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentAttemptOutputDto
{
    /** @param null|string $redirectUrl страница провайдера, пока попытка ожидает оплаты */
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
        public string $paymentMethod,
        public string $orderVersion,
        public bool $latePayment,
        public ?string $redirectUrl,
        public bool $created,
    ) {}
}
