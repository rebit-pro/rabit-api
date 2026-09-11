<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Contract;

interface AuthTransactionInterface
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;
}
