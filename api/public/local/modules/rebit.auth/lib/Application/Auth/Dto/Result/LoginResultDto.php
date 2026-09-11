<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\Dto\Result;

use Rebit\Share\Application\Interface\ResultDtoInterface;

final readonly class LoginResultDto implements ResultDtoInterface
{
    public function __construct(
        public string $token,
        public string $expiresAt,
        public UserDto $user,
    ) {}
}
