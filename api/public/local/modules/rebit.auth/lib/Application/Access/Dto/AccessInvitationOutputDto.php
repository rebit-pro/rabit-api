<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class AccessInvitationOutputDto implements ResultDtoInterface
{
    public function __construct(
        public string $maskedEmail,
        public string $name,
        public string $expiresAt,
    ) {}
}
