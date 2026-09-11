<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Support;

use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;

/** Unit tests only; the MySQL verifier checks real commit/rollback and row locks. */
final readonly class ImmediateTransaction implements AuthTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
