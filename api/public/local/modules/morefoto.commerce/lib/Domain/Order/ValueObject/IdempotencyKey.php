<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\ValueObject;

use Rebit\Share\Shared\Exception\HttpException;

final readonly class IdempotencyKey
{
    public string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $value)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }
        $this->value = strtolower($value);
    }
}
