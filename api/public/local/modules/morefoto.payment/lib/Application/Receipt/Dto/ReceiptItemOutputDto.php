<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Dto;

final readonly class ReceiptItemOutputDto
{
    /**
     * @param int    $amount      цена единицы в копейках
     * @param string $paymentMode full_prepayment | full_payment
     */
    public function __construct(
        public string $description,
        public int $quantity,
        public int $amount,
        public string $paymentMode,
    ) {}
}
