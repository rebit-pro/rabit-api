<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Entity;

final readonly class UserCredentials
{
    public function __construct(
        public int $id,
        public string $passwordHash,
        public string $email,
        public string $name,
    ) {}
}
