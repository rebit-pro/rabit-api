<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Contract;

interface SupportTransactionInterface
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
