<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Contract;

interface PaymentIdGeneratorInterface
{
    /** Случайный UUID v4 в нижнем регистре. */
    public function uuid(): string;
}
