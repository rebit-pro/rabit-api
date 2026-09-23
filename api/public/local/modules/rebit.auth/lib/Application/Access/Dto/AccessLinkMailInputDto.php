<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class AccessLinkMailInputDto
{
    public function __construct(
        public int $userId,
        public string $recipient,
        public string $name,
        public string $token,
        public int $issuedAt,
        public int $expiresAt,
    ) {}
}
