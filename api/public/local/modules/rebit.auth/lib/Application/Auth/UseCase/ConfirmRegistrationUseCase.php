<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use Random\RandomException;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\ConfirmRegistrationRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\Dto\Result\UserDto;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\RepositoryException;

final readonly class ConfirmRegistrationUseCase
{
    public function __construct(
        private UserRepository $userRepository,
        private RegistrationConfirmationRepository $registrationConfirmationRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private int $tokenTtlHours,
        private int $maxAttempts,
    ) {}

    /**
     * @throws HttpException
     * @throws RandomException
     * @throws RepositoryException
     */
    public function execute(ConfirmRegistrationRequestDto $dto): LoginResultDto
    {
        $email = self::normalizeEmail($dto->email);
        $confirmation = $this->registrationConfirmationRepository->findByEmail($email);

        if (null === $confirmation || null !== $confirmation->confirmedAt) {
            throw new HttpException('Код подтверждения не найден. Запросите новый.', 404);
        }

        if ($confirmation->codeExpiresAt->getTimestamp() < time()) {
            throw new HttpException('Срок действия кода истёк. Запросите новый.', 410);
        }

        if ($confirmation->attempts >= $this->maxAttempts) {
            throw new HttpException('Превышено количество попыток. Запросите новый код.', 429);
        }

        if (!password_verify($dto->code, $confirmation->codeHash)) {
            $this->registrationConfirmationRepository->incrementAttempts($confirmation->id);

            throw new HttpException('Неверный код подтверждения.', 400);
        }

        $user = $this->userRepository->findById($confirmation->userId);

        if (null === $user) {
            throw new HttpException('Пользователь для подтверждения не найден.', 404);
        }

        if (false === $user->isActive) {
            $this->userRepository->activateUser($user->id);
        }

        $this->registrationConfirmationRepository->markConfirmed($confirmation->id);

        $token = $this->tokenGenerator->generate();
        $expiresAt = DateTime::createFromTimestamp(
            time() + ($this->tokenTtlHours * 3600),
        );

        $this->userRepository->updateToken($user->id, $token, $expiresAt);

        return new LoginResultDto(
            token: $token,
            expiresAt: $expiresAt->format('c'),
            user: new UserDto(
                id: $user->id,
                email: $user->email,
                name: $user->name,
            ),
        );
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
