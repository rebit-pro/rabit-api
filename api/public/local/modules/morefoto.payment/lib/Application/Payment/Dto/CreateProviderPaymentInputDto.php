<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class CreateProviderPaymentInputDto
{
    /**
     * @param int    $amount         сумма в копейках
     * @param string $idempotenceKey ключ идемпотентности провайдера, сохранённый в попытке
     */
    public function __construct(
        public int $amount,
        public string $paymentMethod,
        public string $description,
        public string $returnUrl,
        public string $attemptId,
        public string $orderId,
        public string $idempotenceKey,
    ) {}
}
