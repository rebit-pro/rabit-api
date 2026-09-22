<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Contract;

interface OrderTransactionInterface
{
    /**
     * Одна локальная транзакция с ограниченным повтором всей операции при deadlock.
     *
     * @template T
     *
     * @param callable():T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
