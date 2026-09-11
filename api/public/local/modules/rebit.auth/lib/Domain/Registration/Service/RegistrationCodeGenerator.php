<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Registration\Service;

use Random\RandomException;

final readonly class RegistrationCodeGenerator
{
    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return str_pad(
            (string)random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT,
        );
    }
}
