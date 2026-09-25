<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Access;

use Random\RandomException;
use Rebit\Auth\Application\Access\Contract\AccessTokenGeneratorInterface;

final readonly class RandomAccessTokenGenerator implements AccessTokenGeneratorInterface
{
    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
