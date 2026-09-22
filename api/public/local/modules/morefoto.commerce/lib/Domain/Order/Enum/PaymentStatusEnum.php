<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Enum;

enum PaymentStatusEnum: string
{
    case UNPAID = 'unpaid';
    case PENDING = 'pending';
    case DECLINED = 'declined';
    case PAID = 'paid';
}
