<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Access\Service;

/**
 * Решает, можно ли принять новый пароль сотрудника: не короче 10 символов и не повторяет email.
 * Правило общее для приглашения, восстановления и смены пароля, чтобы слабый пароль не проходил ни одним путём.
 */
final readonly class PasswordPolicy
{
    public const int MIN_LENGTH = 10;
    public const int MAX_LENGTH = 128;

    public function isAcceptable(string $password, string $email): bool
    {
        $length = mb_strlen($password);
        if (self::MIN_LENGTH > $length || self::MAX_LENGTH < $length || '' === trim($password)) {
            return false;
        }

        return mb_strtolower(trim($password)) !== mb_strtolower(trim($email));
    }
}
