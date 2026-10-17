<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Enum;

/** Признак способа расчёта (тег 1214): полная предоплата до передачи товара или полный расчёт при передаче. */
enum PaymentModeEnum: string
{
    case FULL_PREPAYMENT = 'full_prepayment';
    case FULL_PAYMENT = 'full_payment';
}
