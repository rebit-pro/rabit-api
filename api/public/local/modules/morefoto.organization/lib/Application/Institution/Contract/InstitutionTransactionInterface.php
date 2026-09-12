<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Institution\Contract;

interface InstitutionTransactionInterface
{
    /** @template T
     * @param callable():T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
