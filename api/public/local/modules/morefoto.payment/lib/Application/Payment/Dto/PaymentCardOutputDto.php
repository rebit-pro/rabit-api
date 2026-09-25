<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class PaymentCardOutputDto
{
    /**
     * @param list<PaymentItemOutputDto> $attempts все попытки заказа, новые первыми
     * @param list<PaymentFactOutputDto> $facts    подтверждённые денежные факты заказа
     */
    public function __construct(
        public PaymentItemOutputDto $payment,
        public ?string $providerPaymentId,
        public int $checkCount,
        public ?string $lastCheckAt,
        public ?string $nextCheckAt,
        public array $attempts,
        public array $facts,
    ) {}
}
