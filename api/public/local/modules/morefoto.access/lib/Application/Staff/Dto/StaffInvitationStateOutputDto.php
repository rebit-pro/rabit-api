<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class StaffInvitationStateOutputDto implements ResultDtoInterface
{
    /** @param 'accepted'|'expired'|'sent' $state */
    public function __construct(
        public string $sentAt,
        public string $expiresAt,
        public string $state,
    ) {}
}
