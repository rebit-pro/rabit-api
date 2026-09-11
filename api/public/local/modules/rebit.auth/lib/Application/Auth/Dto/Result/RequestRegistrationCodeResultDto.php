<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Result;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class RequestRegistrationCodeResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $email,
        public string $codeExpiresAt,
        public string $resendAvailableAt,
    ) {}
}
