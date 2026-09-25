<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class StartPaymentInputDto
{
    public function __construct(
        public string $orderVersion,
        public ?string $precedingAttemptId,
        public string $quoteToken,
        public string $paymentMethod,
    ) {}
}
