<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\Enum;

enum ChildSalesStatusEnum: string
{
    case PAID = 'paid';
    case LATE = 'late';
    case UNPAID = 'unpaid';
    case NONE = 'none';
}
