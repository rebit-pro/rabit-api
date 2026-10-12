<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Enum;

/** Column the staff order list is sorted by (#92 DEC-05); the server orders the whole filter, not one page. */
enum OrderSortEnum: string
{
    case CREATED_AT = 'createdAt';
    case BUYER_EMAIL = 'buyerEmail';
    case INSTITUTION_NAME = 'institutionName';
    case TOTAL = 'total';
    case PAYMENT_STATUS = 'paymentStatus';
    case PRODUCTION_STATUS = 'productionStatus';
}
