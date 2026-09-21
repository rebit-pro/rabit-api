<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Storefront\Contract;

interface QuoteTransactionInterface
{
    /** @template T
     * @param callable():T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
