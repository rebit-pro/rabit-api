<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentFactOutputDto
{
    public function __construct(
        public string $attemptId,
        public int $amount,
        public ?int $incomeAmount,
        public string $paidAt,
        public bool $latePayment,
        public string $confirmedBy,
    ) {}
}
