<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptSnapshot;

final readonly class CreateProviderReceiptInputDto
{
    /**
     * Закрывающий чек прихода: позиции полным расчётом с зачётом ранее внесённой предоплаты.
     *
     * @param int    $prepayment     зачитываемая предоплата в копейках
     * @param string $idempotenceKey ключ идемпотентности провайдера, сохранённый в чеке
     */
    public function __construct(
        public string $providerPaymentId,
        public ReceiptSnapshot $receipt,
        public int $prepayment,
        public string $idempotenceKey,
    ) {}
}
