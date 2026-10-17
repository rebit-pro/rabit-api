<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Receipt\Enum;

/** Чек оплаты фиксирует поступление денег, закрывающий — полный расчёт по печати после её выдачи (G2-DEC-02). */
enum ReceiptKindEnum: string
{
    case PAYMENT = 'payment';
    case CLOSING = 'closing';
}
