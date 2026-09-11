<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\UseCase\LogoutUseCase;
use Rebit\Auth\Domain\User\Repository\UserRepository;

/**
 * @internal
 */
final class LogoutUseCaseTest extends TestCase
{
    private MockObject&UserRepository $userRepository;
    private LogoutUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->useCase = new LogoutUseCase(
            userRepository: $this->userRepository,
        );
    }

    public function testSuccessfulLogout(): void
    {
        $userId = 42;

        $this->userRepository
            ->expects($this->once())
            ->method('clearToken')
            ->with($userId)
        ;

        $this->useCase->execute($userId);
    }

    public function testLogoutCallsClearTokenWithCorrectUserId(): void
    {
        $capturedUserId = null;

        $this->userRepository
            ->expects($this->once())
            ->method('clearToken')
            ->willReturnCallback(function(int $userId) use (&$capturedUserId): void {
                $capturedUserId = $userId;
            })
        ;

        $this->useCase->execute(99);

        self::assertSame(99, $capturedUserId);
    }
}
