<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\UseCase\LoginUseCase;
use Rebit\Auth\Domain\User\Entity\UserCredentials;
use Rebit\Share\Shared\Exception\HttpException;

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
    ): LoginUseCase {
        return new LoginUseCase(
            userRepository: $userRepository,
            tokenGenerator: $tokenGenerator,
            captchaVerifier: $captchaVerifier ?? $this->createStub(CaptchaVerifierInterface::class),
            tokenTtlHours: self::TOKEN_TTL_HOURS,
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
            ->method('findActiveByEmail')
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
        $expectedMin = time() + (23 * 3600);
        $expectedMax = time() + (25 * 3600);
        self::assertGreaterThanOrEqual($expectedMin, $capturedExpiresAt->getTimestamp());
        self::assertLessThanOrEqual($expectedMax, $capturedExpiresAt->getTimestamp());
    }
}
