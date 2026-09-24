<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class PasswordResetInputDto
{
    public function __construct(public string $email) {}
}
