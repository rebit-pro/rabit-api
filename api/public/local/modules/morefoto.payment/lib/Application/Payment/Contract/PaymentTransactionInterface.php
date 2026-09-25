<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Contract;

interface PaymentTransactionInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
