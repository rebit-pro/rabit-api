<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use Random\RandomException;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Auth\Application\Auth\Dto\Request\RequestRegistrationCodeRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\RequestRegistrationCodeResultDto;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\Registration\Service\RegistrationCodeGenerator;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\RepositoryException;

final readonly class RequestRegistrationCodeUseCase
{
    public function __construct(
        private UserRepository $userRepository,
        private RegistrationConfirmationRepository $registrationConfirmationRepository,
        private RegistrationCodeGenerator $registrationCodeGenerator,
        private RegistrationConfirmationMailerInterface $registrationConfirmationMailer,
        private int $codeTtlMinutes,
        private int $resendCooldownSeconds,
        private ClockInterface $clock,
        private AuthTransactionInterface $transaction,
    ) {
        if (0 >= $codeTtlMinutes || 0 > $resendCooldownSeconds) {
            throw new \InvalidArgumentException('Registration lifetime configuration is invalid.');
        }
    }

    /**
     * @throws HttpException
     * @throws RandomException
     * @throws RepositoryException
     */
    public function execute(RequestRegistrationCodeRequestDto $dto): RequestRegistrationCodeResultDto
    {
        [$email, $code, $expiresAt, $resendAvailableAt] = $this->transaction->run(
            fn(): array => $this->prepareRegistration($dto),
        );
        // Transport is outside the database transaction; retry uses the normal cooldown.
        $this->registrationConfirmationMailer->sendConfirmationCode($email, $code, $expiresAt);

        return new RequestRegistrationCodeResultDto(
            email: $email,
            codeExpiresAt: $expiresAt->format('c'),
            resendAvailableAt: $resendAvailableAt->format('c'),
        );
    }

    /** @return array{string, string, DateTime, DateTime} */
    private function prepareRegistration(RequestRegistrationCodeRequestDto $dto): array
    {
        $email = self::normalizeEmail($dto->email);
        $existingConfirmation = $this->registrationConfirmationRepository->findByEmail($email, forUpdate: true);
        $existingUser = $this->userRepository->findByEmail($email);
        if (null !== $existingUser) {
            $existingUser = $this->userRepository->findByIdForUpdate($existingUser->id);
        }

        if (null !== $existingUser && true === $existingUser->isActive) {
            throw new HttpException('Пользователь с таким email уже зарегистрирован.', 409);
        }

        if (null !== $existingUser && (!$existingUser->isPendingRegistration || $email !== self::normalizeEmail($existingUser->email))) {
            throw new HttpException('Регистрация для этого пользователя недоступна.', 409);
        }
        if (null !== $existingConfirmation && (
            null === $existingUser
            || $existingUser->id !== $existingConfirmation->userId
            || null !== $existingConfirmation->confirmedAt
        )) {
            throw new HttpException('Регистрация для этого пользователя недоступна.', 409);
        }
        $nowTimestamp = $this->clock->now();

        if (
            null !== $existingConfirmation
            && null === $existingConfirmation->confirmedAt
            && $existingConfirmation->resendAvailableAt->getTimestamp() > $nowTimestamp
        ) {
            throw new HttpException(
                sprintf(
                    'Повторная отправка будет доступна через %d сек.',
                    $existingConfirmation->resendAvailableAt->getTimestamp() - $nowTimestamp,
                ),
                429,
            );
        }

        $userId = null === $existingUser
            ? $this->userRepository->createInactiveUser($email, $dto->password, $email)
            : $existingUser->id;

        if (null !== $existingUser) {
            $this->userRepository->updateInactiveCredentials($existingUser->id, $dto->password, $email);
        }

        $code = $this->registrationCodeGenerator->generate();
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = DateTime::createFromTimestamp($nowTimestamp + ($this->codeTtlMinutes * 60));
        $resendAvailableAt = DateTime::createFromTimestamp($nowTimestamp + $this->resendCooldownSeconds);

        if (null === $existingConfirmation) {
            $this->registrationConfirmationRepository->create(
                userId: $userId,
                email: $email,
                codeHash: $codeHash,
                codeExpiresAt: $expiresAt,
                resendAvailableAt: $resendAvailableAt,
            );
        } else {
            $this->registrationConfirmationRepository->updateForResend(
                id: $existingConfirmation->id,
                userId: $userId,
                codeHash: $codeHash,
                codeExpiresAt: $expiresAt,
                resendAvailableAt: $resendAvailableAt,
            );
        }

        return [$email, $code, $expiresAt, $resendAvailableAt];
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
