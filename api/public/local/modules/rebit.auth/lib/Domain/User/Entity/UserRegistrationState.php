<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Entity;

final readonly class UserRegistrationState
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public bool $isActive,
    ) {}
}
