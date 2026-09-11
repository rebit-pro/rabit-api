<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Contract;

use Random\RandomException;

interface TokenGeneratorInterface
{
    /**
     * @throws RandomException
     */
    public function generate(): string;
}
