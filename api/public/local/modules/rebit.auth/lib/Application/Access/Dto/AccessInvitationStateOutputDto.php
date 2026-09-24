<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

final readonly class AccessInvitationStateOutputDto
{
    /** @param 'accepted'|'expired'|'sent' $state */
    public function __construct(
        public int $userId,
        public string $sentAt,
        public string $expiresAt,
        public string $state,
    ) {}
}
