<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentCardResultDto implements ResultDtoInterface
{
    /**
     * @param list<PaymentItemResultDto> $attempts
     * @param list<PaymentFactResultDto> $facts
     */
    public function __construct(
        public PaymentItemResultDto $payment,
        public ?string $providerPaymentId,
        public int $checkCount,
        public ?string $lastCheckAt,
        public ?string $nextCheckAt,
        public array $attempts,
        public array $facts,
    ) {}
}
