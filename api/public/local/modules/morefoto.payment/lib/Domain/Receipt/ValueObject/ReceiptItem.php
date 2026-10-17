<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\ValueObject;

use Morefoto\Payment\Domain\Receipt\Enum\PaymentModeEnum;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;

/** Позиция кассового чека: название до 128 символов, количество, цена единицы в копейках и налоговые признаки. */
final readonly class ReceiptItem
{
    public function __construct(
        public string $description,
        public int $quantity,
        public int $amount,
        public bool $print,
        public PaymentModeEnum $mode,
        public PaymentSubjectEnum $subject,
        public int $vatCode,
    ) {}

    public function total(): int
    {
        return $this->quantity * $this->amount;
    }
}
