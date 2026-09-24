<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth\Dto;

final readonly class StaffInvitationOutputDto
{
    /** @param 'accepted'|'expired'|'sent' $state */
    public function __construct(
        public int $userId,
        public string $sentAt,
        public string $expiresAt,
        public string $state,
    ) {}
}
