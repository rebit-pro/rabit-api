<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class ChangePasswordInputDto
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
