<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Dto;

final readonly class OrderNameSalesOutputDto
{
    public function __construct(
        public string $id,
        public string $number,
        public ?string $firstName,
        public ?string $lastName,
        public string $paymentStatus,
        public bool $latePayment,
        public int $total,
    ) {}
}
