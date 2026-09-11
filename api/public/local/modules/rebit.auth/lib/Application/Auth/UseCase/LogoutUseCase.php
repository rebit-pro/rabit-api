<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\UseCase;

use Rebit\Auth\Domain\User\Repository\UserRepository;

final readonly class LogoutUseCase
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function execute(int $userId): void
    {
        $this->userRepository->clearToken($userId);
    }
}
