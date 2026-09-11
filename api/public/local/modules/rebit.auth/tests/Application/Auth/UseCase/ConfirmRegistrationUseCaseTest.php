<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\ConfirmRegistrationRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\UseCase\ConfirmRegistrationUseCase;
use Rebit\Auth\Domain\Registration\Entity\RegistrationConfirmation;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class ConfirmRegistrationUseCaseTest extends TestCase
{
    private const int TOKEN_TTL_HOURS = 24;
    private const int MAX_ATTEMPTS = 5;

    private function createUseCase(
        UserRepository $userRepository,
        RegistrationConfirmationRepository $registrationConfirmationRepository,
        TokenGeneratorInterface $tokenGenerator,
    ): ConfirmRegistrationUseCase {
        return new ConfirmRegistrationUseCase(
            userRepository: $userRepository,
            registrationConfirmationRepository: $registrationConfirmationRepository,
            tokenGenerator: $tokenGenerator,
            tokenTtlHours: self::TOKEN_TTL_HOURS,
            maxAttempts: self::MAX_ATTEMPTS,
        );
    }

    public function testActivatesUserAndReturnsLoginResult(): void
    {
        $code = '123456';
        $confirmation = new RegistrationConfirmation(
            id: 7,
            userId: 15,
            email: 'user@example.com',
            codeHash: password_hash($code, PASSWORD_DEFAULT),
            codeExpiresAt: DateTime::createFromTimestamp(time() + 600),
            resendAvailableAt: DateTime::createFromTimestamp(time() - 1),
            attempts: 0,
            confirmedAt: null,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
        );

        $userRepository = $this->createMock(UserRepository::class);
        $registrationConfirmationRepository = $this->createMock(RegistrationConfirmationRepository::class);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn($confirmation)
        ;

        $userRepository
            ->expects($this->once())
            ->method('findById')
            ->with(15)
            ->willReturn(new UserRegistrationState(
                id: 15,
                email: 'user@example.com',
                name: 'user@example.com',
                isActive: false,
            ))
        ;

        $userRepository
            ->expects($this->once())
            ->method('activateUser')
            ->with(15)
        ;

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('markConfirmed')
            ->with(7)
        ;

        $tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('generated-token')
        ;

        $userRepository
            ->expects($this->once())
            ->method('updateToken')
            ->with(
                15,
                'generated-token',
                $this->isInstanceOf(DateTime::class),
            )
        ;

        $result = $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $tokenGenerator,
        )->execute(new ConfirmRegistrationRequestDto(
            email: 'User@example.com',
            code: $code,
        ));

        self::assertInstanceOf(LoginResultDto::class, $result);
        self::assertSame('generated-token', $result->token);
        self::assertSame(15, $result->user->id);
        self::assertSame('user@example.com', $result->user->email);
    }

    public function testIncrementsAttemptsForInvalidCode(): void
    {
        $confirmation = new RegistrationConfirmation(
            id: 8,
            userId: 16,
            email: 'user@example.com',
            codeHash: password_hash('123456', PASSWORD_DEFAULT),
            codeExpiresAt: DateTime::createFromTimestamp(time() + 600),
            resendAvailableAt: DateTime::createFromTimestamp(time() - 1),
            attempts: 0,
            confirmedAt: null,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
        );

        $userRepository = $this->createStub(UserRepository::class);
        $registrationConfirmationRepository = $this->createMock(RegistrationConfirmationRepository::class);
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn($confirmation)
        ;

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('incrementAttempts')
            ->with(8)
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Неверный код подтверждения.');
        $this->expectExceptionCode(400);

        $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $tokenGenerator,
        )->execute(new ConfirmRegistrationRequestDto(
            email: 'user@example.com',
            code: '654321',
        ));
    }

    public function testThrowsWhenCodeExpired(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $registrationConfirmationRepository = $this->createMock(RegistrationConfirmationRepository::class);
        $tokenGenerator = $this->createStub(TokenGeneratorInterface::class);

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(new RegistrationConfirmation(
                id: 9,
                userId: 17,
                email: 'user@example.com',
                codeHash: password_hash('123456', PASSWORD_DEFAULT),
                codeExpiresAt: DateTime::createFromTimestamp(time() - 5),
                resendAvailableAt: DateTime::createFromTimestamp(time() - 1),
                attempts: 0,
                confirmedAt: null,
                createdAt: new DateTime(),
                updatedAt: new DateTime(),
            ))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Срок действия кода истёк. Запросите новый.');
        $this->expectExceptionCode(410);

        $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $tokenGenerator,
        )->execute(new ConfirmRegistrationRequestDto(
            email: 'user@example.com',
            code: '123456',
        ));
    }
}
