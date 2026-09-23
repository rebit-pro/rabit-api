<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Service;

use Bitrix\Main\Type\DateTime;
use Random\RandomException;
use Rebit\Auth\Application\Access\Dto\AccessAccountDto;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Application\Auth\Dto\Result\UserDto;

/**
 * Открывает сессию сразу после установки пароля по ссылке, чтобы сотруднику не нужно было входить повторно.
 * Сессия у пользователя одна: новый токен заменяет прежний, и остальные устройства выходят из кабинета.
 */
final readonly class SessionIssuer
{
    public function __construct(
        private TokenGeneratorInterface $tokens,
        private LoginUserRepositoryInterface $users,
        private ClockInterface $clock,
        private int $tokenTtlHours,
    ) {
        if (0 >= $tokenTtlHours) {
            throw new \InvalidArgumentException('Token lifetime must be positive.');
        }
    }

    /**
     * @throws RandomException
     */
    public function issue(AccessAccountDto $account): LoginResultDto
    {
        $token = $this->tokens->generate();
        $expiresAt = DateTime::createFromTimestamp($this->clock->now() + ($this->tokenTtlHours * 3600));
        $this->users->updateToken($account->id, $token, $expiresAt);

        return new LoginResultDto(
            token: $token,
            expiresAt: $expiresAt->format('c'),
            user: new UserDto(id: $account->id, email: $account->email, name: $account->name),
        );
    }
}
