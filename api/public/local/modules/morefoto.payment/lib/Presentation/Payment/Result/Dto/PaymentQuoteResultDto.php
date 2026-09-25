<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment\Result\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class PaymentQuoteResultDto implements ResultDtoInterface
{
    /**
     * @param array{subtotal: int, discount: int, giftSaving: int, total: int, currency: string} $quote
     * @param list<string>                                                                       $paymentMethods
     */
    public function __construct(
        public array $quote,
        public string $quoteToken,
        public string $orderVersion,
        public bool $canPay,
        public ?string $precedingAttemptId,
        public ?string $activeAttemptId,
        public array $paymentMethods,
    ) {}
}
