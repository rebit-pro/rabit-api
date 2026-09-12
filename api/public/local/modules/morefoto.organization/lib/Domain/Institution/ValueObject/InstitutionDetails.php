<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Institution\ValueObject;

use Morefoto\Organization\Domain\Institution\Exception\InvalidInstitutionException;

final readonly class InstitutionDetails
{
    public string $name;
    public string $address;

    public function __construct(string $name, string $address)
    {
        $hasNul = str_contains($name, "\0") || str_contains($address, "\0");
        $name = trim($name);
        $address = trim($address);
        if (!mb_check_encoding($name, 'UTF-8') || !mb_check_encoding($address, 'UTF-8')
            || '' === $name || 255 < mb_strlen($name) || 500 < mb_strlen($address)
            || $hasNul) {
            throw new InvalidInstitutionException('Name must contain 1–255 characters; address at most 500, both valid UTF-8 without NUL.');
        }
        $this->name = $name;
        $this->address = $address;
    }
}
