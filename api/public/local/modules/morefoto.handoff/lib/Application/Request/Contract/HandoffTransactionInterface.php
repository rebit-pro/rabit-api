<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\Contract;

interface HandoffTransactionInterface
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
