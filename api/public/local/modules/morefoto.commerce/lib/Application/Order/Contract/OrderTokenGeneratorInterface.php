<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Contract;

interface OrderTokenGeneratorInterface
{
    /** Публичный UUID v4 заказа или строки. */
    public function uuid(): string;

    /** Личный ключ заказа: 32 криптографически случайных байта в 64 hex. */
    public function secret(): string;
}
