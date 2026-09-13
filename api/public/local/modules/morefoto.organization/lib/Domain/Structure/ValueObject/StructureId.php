<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\ValueObject;

use Ramsey\Uuid\Uuid;

final readonly class StructureId
{
    public string $value;

    public function __construct(string $value)
    {
        if (36 !== strlen($value) || !Uuid::isValid($value)) {
            throw new \InvalidArgumentException('A canonical UUID is required.');
        }
        $this->value = strtolower($value);
    }
}
