<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class OrderNameSalesResultDto implements ResultDtoInterface
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
