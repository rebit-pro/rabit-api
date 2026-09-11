<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Institution\ValueObject;

use Morefoto\Organization\Domain\Institution\Exception\InvalidInstitutionException;
use Ramsey\Uuid\Uuid;

final readonly class InstitutionId
{
    public string $value;

    public function __construct(string $value)
    {
        if (36 !== strlen($value) || !Uuid::isValid($value)) {
            throw new InvalidInstitutionException('Institution ID must be a canonical UUID.');
        }
        $this->value = strtolower($value);
    }
}
