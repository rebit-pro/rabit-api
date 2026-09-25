<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentFactResultDto implements ResultDtoInterface
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
