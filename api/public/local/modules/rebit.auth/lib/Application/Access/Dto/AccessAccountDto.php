<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class AccessAccountDto
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public bool $active,
        public bool $pending,
        public string $passwordHash,
    ) {}
}
