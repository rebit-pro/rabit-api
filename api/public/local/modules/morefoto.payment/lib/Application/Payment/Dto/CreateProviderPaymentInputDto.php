<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;

final readonly class CreateProviderPaymentInputDto
{
    /**
     * @param int                  $amount         сумма в копейках
     * @param string               $idempotenceKey ключ идемпотентности провайдера, сохранённый в попытке
     * @param null|ReceiptSnapshot $receipt        чек оплаты из снимка попытки (G2); null — чеки выключены
     */
    public function __construct(
        public int $amount,
        public string $paymentMethod,
        public string $description,
        public string $returnUrl,
        public string $attemptId,
        public string $orderId,
        public string $idempotenceKey,
        public ?ReceiptSnapshot $receipt = null,
    ) {}
}
