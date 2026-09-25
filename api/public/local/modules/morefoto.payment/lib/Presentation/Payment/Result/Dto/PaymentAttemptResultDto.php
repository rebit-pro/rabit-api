<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentAttemptResultDto implements ResultDtoInterface
{
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
