<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use PHPUnit\Framework\TestCase;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Auth\Application\Auth\Dto\Request\RequestRegistrationCodeRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\RequestRegistrationCodeResultDto;
use Rebit\Auth\Application\Auth\UseCase\RequestRegistrationCodeUseCase;
use Rebit\Auth\Domain\Registration\Entity\RegistrationConfirmation;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\Registration\Service\RegistrationCodeGenerator;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * @internal
 */
final class RequestRegistrationCodeUseCaseTest extends TestCase
{
    private const int CODE_TTL_MINUTES = 15;
    private const int RESEND_COOLDOWN_SECONDS = 60;

    private function createUseCase(
        UserRepository $userRepository,
        RegistrationConfirmationRepository $registrationConfirmationRepository,
        RegistrationCodeGenerator $registrationCodeGenerator,
        RegistrationConfirmationMailerInterface $registrationConfirmationMailer,
    ): RequestRegistrationCodeUseCase {
        return new RequestRegistrationCodeUseCase(
            userRepository: $userRepository,
            registrationConfirmationRepository: $registrationConfirmationRepository,
            registrationCodeGenerator: $registrationCodeGenerator,
            registrationConfirmationMailer: $registrationConfirmationMailer,
            codeTtlMinutes: self::CODE_TTL_MINUTES,
            resendCooldownSeconds: self::RESEND_COOLDOWN_SECONDS,
        );
    }

    public function testCreatesInactiveUserAndSendsConfirmationCode(): void
    {
        $dto = new RequestRegistrationCodeRequestDto(
            email: 'User@Example.com',
            password: 'secret123',
        );

        $userRepository = $this->createMock(UserRepository::class);
        $registrationConfirmationRepository = $this->createMock(RegistrationConfirmationRepository::class);
        $registrationCodeGenerator = $this->createMock(RegistrationCodeGenerator::class);
        $registrationConfirmationMailer = $this->createMock(RegistrationConfirmationMailerInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(null)
        ;

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(null)
        ;

        $userRepository
            ->expects($this->once())
            ->method('createInactiveUser')
            ->with('user@example.com', 'secret123', 'user@example.com')
            ->willReturn(42)
        ;

        $registrationCodeGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('123456')
        ;

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                42,
                'user@example.com',
                $this->isString(),
                $this->isInstanceOf(DateTime::class),
                $this->isInstanceOf(DateTime::class),
            )
        ;

        $registrationConfirmationMailer
            ->expects($this->once())
            ->method('sendConfirmationCode')
            ->with(
                'user@example.com',
                '123456',
                $this->isInstanceOf(DateTime::class),
            )
        ;

        $result = $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $registrationCodeGenerator,
            $registrationConfirmationMailer,
        )->execute($dto);

        self::assertInstanceOf(RequestRegistrationCodeResultDto::class, $result);
        self::assertSame('user@example.com', $result->email);
        self::assertNotEmpty($result->codeExpiresAt);
        self::assertNotEmpty($result->resendAvailableAt);
    }

    public function testThrowsConflictForAlreadyActiveUser(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $registrationConfirmationRepository = $this->createStub(RegistrationConfirmationRepository::class);
        $registrationCodeGenerator = $this->createStub(RegistrationCodeGenerator::class);
        $registrationConfirmationMailer = $this->createStub(RegistrationConfirmationMailerInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(new UserRegistrationState(
                id: 10,
                email: 'user@example.com',
                name: 'User',
                isActive: true,
            ))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Пользователь с таким email уже зарегистрирован.');
        $this->expectExceptionCode(409);

        $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $registrationCodeGenerator,
            $registrationConfirmationMailer,
        )->execute(new RequestRegistrationCodeRequestDto(
            email: 'user@example.com',
            password: 'secret123',
        ));
    }

    public function testThrowsTooManyRequestsWhenResendCooldownIsActive(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $registrationConfirmationRepository = $this->createMock(RegistrationConfirmationRepository::class);
        $registrationCodeGenerator = $this->createStub(RegistrationCodeGenerator::class);
        $registrationConfirmationMailer = $this->createStub(RegistrationConfirmationMailerInterface::class);

        $userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(new UserRegistrationState(
                id: 11,
                email: 'user@example.com',
                name: 'user@example.com',
                isActive: false,
            ))
        ;

        $registrationConfirmationRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(new RegistrationConfirmation(
                id: 5,
                userId: 11,
                email: 'user@example.com',
                codeHash: 'hash',
                codeExpiresAt: DateTime::createFromTimestamp(time() + 600),
                resendAvailableAt: DateTime::createFromTimestamp(time() + 30),
                attempts: 0,
                confirmedAt: null,
                createdAt: new DateTime(),
                updatedAt: new DateTime(),
            ))
        ;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(429);

        $this->createUseCase(
            $userRepository,
            $registrationConfirmationRepository,
            $registrationCodeGenerator,
            $registrationConfirmationMailer,
        )->execute(new RequestRegistrationCodeRequestDto(
            email: 'user@example.com',
            password: 'secret123',
        ));
    }
}
