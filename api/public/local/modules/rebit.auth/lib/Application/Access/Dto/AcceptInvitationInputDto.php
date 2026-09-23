<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class AcceptInvitationInputDto
{
    public function __construct(
        public string $token,
        public string $password,
    ) {}
}
