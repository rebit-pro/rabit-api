<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Contract;

interface AccessTokenGeneratorInterface
{
    /** URL-safe secret of 256 random bits. */
    public function generate(): string;
}
