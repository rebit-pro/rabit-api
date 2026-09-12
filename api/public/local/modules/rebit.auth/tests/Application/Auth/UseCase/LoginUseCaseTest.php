<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Tests\Support\FrozenClock;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\UseCase\LoginUseCase;
use Rebit\Auth\Domain\User\Entity\UserCredentials;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\RepositoryException;

/**
 * @internal
 */
final class LoginUseCaseTest extends TestCase
{
    private const int TOKEN_TTL_HOURS = 24;

    private function createUseCase(
        LoginUserRepositoryInterface $userRepository,
        TokenGeneratorInterface $tokenGenerator,
        ?CaptchaVerifierInterface $captchaVerifier = null,
        ?AuthTransactionInterface $transaction = null,
    ): LoginUseCase {
        if (null === $transaction) {
            $transaction = $this->createStub(AuthTransactionInterface::class);
            $transaction->method('run')->willReturnCallback(static fn(callable $operation): mixed => $operation());
        }

        return new LoginUseCase(
            userRepository: $userRepository,
            tokenGenerator: $tokenGenerator,
            captchaVerifier: $captchaVerifier ?? $this->createStub(CaptchaVerifierInterface::class),
            tokenTtlHours: self::TOKEN_TTL_HOURS,
            clock: new FrozenClock(),
            transaction: $transaction,
        );
    }

    private function createCaptchaDto(): LoginCaptchaRequestDto
    {
        return new LoginCaptchaRequestDto(
            lot_number: 'lot-number',
            captcha_output: 'captcha-output',
            pass_token: 'pass-token',
            gen_time: '1710000000',
        );
    }

    public function testSuccessfulLogin(): void
    {
        $email = 'user@example.com';
        $password = 'secret123';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $generatedToken = bin2hex(random_bytes(16));

        $credentials = new UserCredentials(id: 1, passwordHash: $passwordHash, email: $email, name: 'Test User');

        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createMock(CaptchaVerifierInterface::class);

        $captchaVerifier
            ->expects($this->once())
            ->method('verify')
            ->with($this->equalTo($this->createCaptchaDto()))
        ;

        $userRepository
            ->expects($this->once())
            ->method('findActiveByEmailForUpdate')
            ->with($email)
            ->willReturn($credentials)
        ;

        $tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn($generatedToken)
        ;

        $userRepository
            ->expects($this->once())
            ->method('updateToken')
            ->with(
                1,
                $generatedToken,
                $this->isInstanceOf(DateTime::class),
            )
        ;

        $result = $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: $email, password: $password, captcha: $this->createCaptchaDto()))
        ;

        self::assertInstanceOf(LoginResultDto::class, $result);
        self::assertSame($generatedToken, $result->token);
        self::assertNotEmpty($result->expiresAt);
        self::assertSame(1, $result->user->id);
        self::assertSame($email, $result->user->email);
        self::assertSame('Test User', $result->user->name);
    }

    public function testLoginWithNonExistentEmailThrows401(): void
    {
        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createStub(CaptchaVerifierInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findActiveByEmailForUpdate')
            ->with('unknown@example.com')
            ->willReturn(null)
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->expectExceptionCode(401);

        $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: 'unknown@example.com', password: 'any', captcha: $this->createCaptchaDto()))
        ;
    }

    public function testLoginWithWrongPasswordThrows401(): void
    {
        $credentials = new UserCredentials(
            id: 1,
            passwordHash: password_hash('correct_password', PASSWORD_DEFAULT),
            email: 'user@example.com',
            name: 'Test User',
        );

        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createStub(CaptchaVerifierInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findActiveByEmailForUpdate')
            ->with('user@example.com')
            ->willReturn($credentials)
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->expectExceptionCode(401);

        $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: 'user@example.com', password: 'wrong_password', captcha: $this->createCaptchaDto()))
        ;
    }

    public function testTokenGeneratorNotCalledOnInvalidCredentials(): void
    {
        $userRepository = $this->createStub(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createStub(CaptchaVerifierInterface::class);

        $userRepository
            ->method('findActiveByEmailForUpdate')
            ->willReturn(null)
        ;

        $tokenGenerator
            ->expects($this->never())
            ->method('generate')
        ;

        try {
            $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
                ->execute(new LoginRequestDto(email: 'x@x.com', password: 'x', captcha: $this->createCaptchaDto()))
            ;
        } catch (HttpException) {
            // expected
        }
    }

    public function testTokenGeneratorNotCalledWhenCaptchaVerificationFails(): void
    {
        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createMock(CaptchaVerifierInterface::class);

        $captchaVerifier
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(new HttpException('Captcha verification failed', 400))
        ;

        $userRepository
            ->expects($this->never())
            ->method('findActiveByEmailForUpdate')
        ;

        $tokenGenerator
            ->expects($this->never())
            ->method('generate')
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Captcha verification failed');
        $this->expectExceptionCode(400);

        $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: 'user@example.com', password: 'secret', captcha: $this->createCaptchaDto()))
        ;
    }

    public function testLoginFailsWhenCaptchaServiceUnavailable(): void
    {
        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createMock(CaptchaVerifierInterface::class);

        $captchaVerifier
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(new HttpException('Captcha service unavailable', 503))
        ;

        $userRepository
            ->expects($this->never())
            ->method('findActiveByEmailForUpdate')
        ;

        $tokenGenerator
            ->expects($this->never())
            ->method('generate')
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Captcha service unavailable');
        $this->expectExceptionCode(503);

        $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: 'user@example.com', password: 'secret', captcha: $this->createCaptchaDto()))
        ;
    }

    public function testTokenExpiresAtIsInFuture(): void
    {
        $password = 'secret';
        $credentials = new UserCredentials(
            id: 5,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            email: 'a@b.com',
            name: 'Test User',
        );

        $userRepository = $this->createMock(LoginUserRepositoryInterface::class);
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);
        $captchaVerifier = $this->createStub(CaptchaVerifierInterface::class);

        $userRepository
            ->method('findActiveByEmailForUpdate')
            ->willReturn($credentials)
        ;

        $tokenGenerator
            ->method('generate')
            ->willReturn('abc123')
        ;

        $capturedExpiresAt = null;
        $userRepository
            ->expects($this->once())
            ->method('updateToken')
            ->willReturnCallback(function(int $userId, string $token, DateTime $expiresAt) use (&$capturedExpiresAt): void {
                $capturedExpiresAt = $expiresAt;
            })
        ;

        $this->createUseCase($userRepository, $tokenGenerator, $captchaVerifier)
            ->execute(new LoginRequestDto(email: 'a@b.com', password: $password, captcha: $this->createCaptchaDto()))
        ;

        self::assertNotNull($capturedExpiresAt);
        /** @var DateTime $capturedExpiresAt */
        $expectedMin = (new FrozenClock())->now() + (23 * 3600);
        $expectedMax = (new FrozenClock())->now() + (25 * 3600);
        self::assertGreaterThanOrEqual($expectedMin, $capturedExpiresAt->getTimestamp());
        self::assertLessThanOrEqual($expectedMax, $capturedExpiresAt->getTimestamp());
    }

    public function testCredentialReadAndTokenWriteShareOneTransaction(): void
    {
        $insideTransaction = false;
        $transaction = $this->createMock(AuthTransactionInterface::class);
        $transaction->expects($this->once())->method('run')->willReturnCallback(
            static function(callable $operation) use (&$insideTransaction): mixed {
                $insideTransaction = true;
                try {
                    return $operation();
                } finally {
                    $insideTransaction = false;
                }
            },
        );
        $captcha = $this->createMock(CaptchaVerifierInterface::class);
        $captcha->expects($this->once())->method('verify')->willReturnCallback(
            static function(LoginCaptchaRequestDto $dto) use (&$insideTransaction): void {
                self::assertFalse($insideTransaction, 'External captcha runs before the transaction.');
            },
        );
        $credentials = new UserCredentials(1, password_hash('correct', PASSWORD_DEFAULT), 'staff@example.test', 'Staff');
        $repository = $this->createMock(LoginUserRepositoryInterface::class);
        $repository->expects($this->never())->method('findActiveByEmail');
        $repository->expects($this->once())->method('findActiveByEmailForUpdate')->willReturnCallback(
            static function(string $email) use (&$insideTransaction, $credentials): UserCredentials {
                self::assertTrue($insideTransaction);

                return $credentials;
            },
        );
        $repository->expects($this->once())->method('updateToken')->willReturnCallback(
            static function(int $userId, string $token, DateTime $expiresAt) use (&$insideTransaction): void {
                self::assertTrue($insideTransaction, 'Identity lock must remain held until token persistence.');
            },
        );
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $generator->method('generate')->willReturn('token-issued-under-lock');

        $result = $this->createUseCase($repository, $generator, $captcha, $transaction)
            ->execute(new LoginRequestDto('staff@example.test', 'correct', $this->createCaptchaDto()))
        ;

        self::assertSame('token-issued-under-lock', $result->token);
        self::assertFalse($insideTransaction);
    }

    public function testIdentityDisabledBeforeLockCannotIssueToken(): void
    {
        $active = true;
        $transaction = $this->createMock(AuthTransactionInterface::class);
        $transaction->expects($this->once())->method('run')->willReturnCallback(
            static function(callable $operation) use (&$active): mixed {
                $active = false;

                return $operation();
            },
        );
        $credentials = new UserCredentials(1, password_hash('correct', PASSWORD_DEFAULT), 'staff@example.test', 'Staff');
        $repository = $this->createMock(LoginUserRepositoryInterface::class);
        $repository->expects($this->never())->method('findActiveByEmail');
        $repository->expects($this->once())->method('findActiveByEmailForUpdate')->willReturnCallback(
            static function(string $email) use (&$active, $credentials): ?UserCredentials {
                return $active ? $credentials : null;
            },
        );
        $repository->expects($this->never())->method('updateToken');
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $generator->expects($this->never())->method('generate');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);
        $this->createUseCase($repository, $generator, transaction: $transaction)
            ->execute(new LoginRequestDto('staff@example.test', 'correct', $this->createCaptchaDto()))
        ;
    }

    public function testPasswordChangedBeforeLockRejectsOldPassword(): void
    {
        $storedPasswordHash = password_hash('old-password', PASSWORD_DEFAULT);
        $replacementPasswordHash = password_hash('new-password', PASSWORD_DEFAULT);
        $transaction = $this->createMock(AuthTransactionInterface::class);
        $transaction->expects($this->once())->method('run')->willReturnCallback(
            static function(callable $operation) use (&$storedPasswordHash, $replacementPasswordHash): mixed {
                $storedPasswordHash = $replacementPasswordHash;

                return $operation();
            },
        );
        $repository = $this->createMock(LoginUserRepositoryInterface::class);
        $repository->expects($this->never())->method('findActiveByEmail');
        $repository->expects($this->once())->method('findActiveByEmailForUpdate')->willReturnCallback(
            static function(string $email) use (&$storedPasswordHash): UserCredentials {
                return new UserCredentials(1, $storedPasswordHash, $email, 'Staff');
            },
        );
        $repository->expects($this->never())->method('updateToken');
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $generator->expects($this->never())->method('generate');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);
        $this->createUseCase($repository, $generator, transaction: $transaction)
            ->execute(new LoginRequestDto('staff@example.test', 'old-password', $this->createCaptchaDto()))
        ;
    }

    public function testTokenWriteFailureReachesTransactionRollback(): void
    {
        $rolledBack = false;
        $transaction = $this->createMock(AuthTransactionInterface::class);
        $transaction->expects($this->once())->method('run')->willReturnCallback(
            static function(callable $operation) use (&$rolledBack): mixed {
                try {
                    return $operation();
                } catch (\Throwable $exception) {
                    $rolledBack = true;

                    throw $exception;
                }
            },
        );
        $credentials = new UserCredentials(1, password_hash('correct', PASSWORD_DEFAULT), 'staff@example.test', 'Staff');
        $repository = $this->createMock(LoginUserRepositoryInterface::class);
        $repository->method('findActiveByEmailForUpdate')->willReturn($credentials);
        $failure = new RepositoryException('Token persistence failed.');
        $repository->expects($this->once())->method('updateToken')->willThrowException($failure);
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $generator->method('generate')->willReturn('token-not-returned');

        try {
            $this->createUseCase($repository, $generator, transaction: $transaction)
                ->execute(new LoginRequestDto('staff@example.test', 'correct', $this->createCaptchaDto()))
            ;
            self::fail('Failed token persistence must not return a session.');
        } catch (RepositoryException $exception) {
            self::assertSame($failure, $exception);
            self::assertTrue($rolledBack);
        }
    }
}
