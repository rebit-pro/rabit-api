<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Auth\UseCase;

use Bitrix\Main\Type\DateTime;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\Dto\Result\UserDto;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\RepositoryException;
use Random\RandomException;

final readonly class LoginUseCase
{
    public function __construct(
        private LoginUserRepositoryInterface $userRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private CaptchaVerifierInterface $captchaVerifier,
        private int $tokenTtlHours,
    ) {}

    /**
     * @throws HttpException
     * @throws RepositoryException
     * @throws RandomException
     */
    public function execute(LoginRequestDto $dto): LoginResultDto
    {
        $this->captchaVerifier->verify($dto->captcha);

        $user = $this->userRepository->findActiveByEmail($dto->email);

        if (null === $user) {
            throw new HttpException('Invalid credentials', 401);
        }

        if (!password_verify($dto->password, $user->passwordHash)) {
            throw new HttpException('Invalid credentials', 401);
        }

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
}
