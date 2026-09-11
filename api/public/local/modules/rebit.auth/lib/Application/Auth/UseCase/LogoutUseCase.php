<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\UseCase;

use Rebit\Share\Application\Contract\Auth\TokenRevokerInterface;

final readonly class LogoutUseCase
{
    public function __construct(
        private TokenRevokerInterface $userRepository,
    ) {}

    public function execute(int $userId, string $token): void
    {
        $this->userRepository->revokeToken($userId, $token);
    }
}
