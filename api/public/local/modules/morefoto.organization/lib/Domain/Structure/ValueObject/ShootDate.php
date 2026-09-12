<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\ValueObject;

final readonly class ShootDate
{
    public function __construct(public ?string $value)
    {
        if (null === $value) {
            return;
        }
        if (1 !== preg_match('/^([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})$/D', $value, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            throw new \InvalidArgumentException('A real ISO calendar date or null is required.');
        }
    }
}
