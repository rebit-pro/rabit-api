<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Enum;

/** Column the payment registry is sorted by (#92 DEC-05); the server orders the whole filter, not one page. */
enum PaymentSortEnum: string
{
    case CREATED_AT = 'createdAt';
    case ORDER_NUMBER = 'orderNumber';
    case PAYMENT_METHOD = 'paymentMethod';
    case AMOUNT = 'amount';
    case STATUS = 'status';
}
