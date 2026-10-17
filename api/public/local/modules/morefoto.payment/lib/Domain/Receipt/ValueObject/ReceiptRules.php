<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\ValueObject;

use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;

/** Налоговые параметры позиции чека из настроек магазина; система налогообложения задаётся в кабинете провайдера. */
final readonly class ReceiptRules
{
    public const int MIN_VAT_CODE = 1;
    public const int MAX_VAT_CODE = 12;

    /** @param int $vatCode ставка НДС провайдера: 1 — «Без НДС», не ставка 0% */
    public function __construct(
        public int $vatCode,
        public PaymentSubjectEnum $printSubject,
        public PaymentSubjectEnum $digitalSubject,
    ) {
        if (self::MIN_VAT_CODE > $vatCode || self::MAX_VAT_CODE < $vatCode) {
            throw new \InvalidArgumentException('VAT code must be between 1 and 12.');
        }
    }
}
