<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit\Double;

use Morefoto\Support\Application\Question\Contract\SupportTransactionInterface;

final class ImmediateTransaction implements SupportTransactionInterface
{
    public int $runs = 0;

    public function execute(callable $operation): mixed
    {
        ++$this->runs;

        return $operation();
    }
}
