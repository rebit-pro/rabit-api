<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\Service;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Tests\Support\FrozenClock;
use Rebit\Auth\Infrastructure\Adapter\TokenResolver;
use Rebit\Auth\Domain\User\Entity\UserToken;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class TokenResolverTest extends TestCase
{
    private Stub&UserRepository $userRepository;
    private TokenResolver $tokenResolver;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->tokenResolver = new TokenResolver(
            repository: $this->userRepository,
            clock: new FrozenClock(),
        );
    }

    public function testResolveUserIdWithValidToken(): void
    {
        $token = 'valid-token-abc';
        $expiresAt = DateTime::createFromTimestamp((new FrozenClock())->now() + 3600);

        $this->userRepository
            ->method('findByToken')
            ->willReturn(new UserToken(userId: 10, expiresAt: $expiresAt))
        ;

        $userId = $this->tokenResolver->resolveUserId($token);

        self::assertSame(10, $userId);
    }

    public function testResolveUserIdWithNullExpiresAt(): void
    {
        $token = 'perpetual-token';

        $this->userRepository
            ->method('findByToken')
            ->willReturn(new UserToken(userId: 7, expiresAt: null))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);
        $this->tokenResolver->resolveUserId($token);
    }

    public function testTokenNotFoundThrows401(): void
    {
        $this->userRepository
            ->method('findByToken')
            ->willReturn(null)
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('SESSION_REVOKED');
        $this->expectExceptionCode(401);

        $this->tokenResolver->resolveUserId('nonexistent-token');
    }

    public function testExpiredTokenThrows401(): void
    {
        $expiredAt = DateTime::createFromTimestamp((new FrozenClock())->now() - 3600);

        $this->userRepository
            ->method('findByToken')
            ->willReturn(new UserToken(userId: 3, expiresAt: $expiredAt))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('TOKEN_EXPIRED');
        $this->expectExceptionCode(401);

        $this->tokenResolver->resolveUserId('expired-token');
    }

    public function testTokenExpiringExactlyNowThrows401(): void
    {
        $expiredAt = DateTime::createFromTimestamp((new FrozenClock())->now());

        $this->userRepository
            ->method('findByToken')
            ->willReturn(new UserToken(userId: 1, expiresAt: $expiredAt))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $this->tokenResolver->resolveUserId('edge-token');
    }

    public function testEmptyTokenNeverQueriesRepository(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('findByToken');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('UNAUTHORIZED');
        $this->expectExceptionCode(401);
        (new TokenResolver($repository, new FrozenClock()))->resolveUserId('');
    }

    public function testOneSecondBeforeExpiryIsStillValid(): void
    {
        $this->userRepository->method('findByToken')->willReturn(new UserToken(7, DateTime::createFromTimestamp((new FrozenClock())->now() + 1)));
        self::assertSame(7, $this->tokenResolver->resolveUserId('valid'));
    }
}
