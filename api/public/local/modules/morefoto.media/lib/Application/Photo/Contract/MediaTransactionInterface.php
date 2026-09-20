<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Photo\Contract;

interface MediaTransactionInterface
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
