<?php

declare(strict_types=1);

namespace Morefoto\Payment\Domain\Payment\Enum;

enum PaymentMethodEnum: string
{
    case SBP = 'sbp';
    case BANK_CARD = 'bank_card';
}
