<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\ValueObject;

use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;

final readonly class IdempotencyKey
{
    public string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $value)) {
            throw new InvalidProductException('Idempotency-Key must contain exactly 32 hexadecimal characters.');
        }
        $this->value = strtolower($value);
    }
}
