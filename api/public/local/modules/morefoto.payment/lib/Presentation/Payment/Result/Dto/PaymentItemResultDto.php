<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentItemResultDto implements ResultDtoInterface
{
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
