<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\Contract;

interface OrderDownloadGuardInterface
{
    /**
     * Выполняет операцию под блокировкой запросов скачивания заказа и в одной транзакции:
     * ключ идемпотентности, выбор загрузки и её создание фиксируются вместе или не фиксируются вовсе.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function atomically(int $orderId, callable $operation): mixed;
}
