<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Random\RandomException;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;

final readonly class TokenGenerator implements TokenGeneratorInterface
{
    /**
     * Генерирует криптографически стойкий токен.
     *
     * @throws RandomException
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
