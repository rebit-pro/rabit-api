<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Repository;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Domain\User\Entity\UserCredentials;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Entity\UserToken;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

final readonly class UserRepository implements LoginUserRepositoryInterface
{
    use RepositoryExceptionTrait;

    /**
     * @throws RepositoryException
     */
    public function findByToken(string $token): ?UserToken
    {
        return $this->query(function() use ($token): ?UserToken {
            $row = UserTable::query()
                ->setSelect(['ID', 'UF_TOKEN_EXPIRES_AT'])
                ->where('UF_TOKEN', $token)
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row) {
                return null;
            }

            return new UserToken(
                userId: (int)$row['ID'],
                expiresAt: $row['UF_TOKEN_EXPIRES_AT'] instanceof DateTime
                    ? $row['UF_TOKEN_EXPIRES_AT']
                    : null,
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function findActiveByEmail(string $email): ?UserCredentials
    {
        return $this->query(function() use ($email): ?UserCredentials {
            $row = UserTable::query()
                ->setSelect(['ID', 'PASSWORD', 'EMAIL', 'NAME'])
                ->enablePrivateFields()
                ->where('EMAIL', $email)
                ->where('ACTIVE', 'Y')
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row) {
                return null;
            }

            return new UserCredentials(
                id: (int)$row['ID'],
                passwordHash: (string)$row['PASSWORD'],
                email: (string)$row['EMAIL'],
                name: (string)$row['NAME'],
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function findByEmail(string $email): ?UserRegistrationState
    {
        return $this->query(function() use ($email): ?UserRegistrationState {
            $row = UserTable::query()
                ->setSelect(['ID', 'EMAIL', 'NAME', 'ACTIVE'])
                ->where('EMAIL', $email)
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row) {
                return null;
            }

            return new UserRegistrationState(
                id: (int)$row['ID'],
                email: (string)$row['EMAIL'],
                name: (string)$row['NAME'],
                isActive: 'Y' === (string)$row['ACTIVE'],
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function findById(int $userId): ?UserRegistrationState
    {
        return $this->query(function() use ($userId): ?UserRegistrationState {
            $row = UserTable::query()
                ->setSelect(['ID', 'EMAIL', 'NAME', 'ACTIVE'])
                ->where('ID', $userId)
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row) {
                return null;
            }

            return new UserRegistrationState(
                id: (int)$row['ID'],
                email: (string)$row['EMAIL'],
                name: (string)$row['NAME'],
                isActive: 'Y' === (string)$row['ACTIVE'],
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function createInactiveUser(string $email, string $password, string $name): int
    {
        $user = new \CUser();
        $userId = $user->Add([
            'LOGIN' => $email,
            'EMAIL' => $email,
            'NAME' => $name,
            'ACTIVE' => 'N',
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
        ]);

        if (false === $userId) {
            throw new RepositoryException((string)$user->LAST_ERROR);
        }

        return (int)$userId;
    }

    /**
     * @throws RepositoryException
     */
    public function updateInactiveCredentials(int $userId, string $password, string $name): void
    {
        $this->updateUser($userId, [
            'NAME' => $name,
            'ACTIVE' => 'N',
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
        ]);
    }

    /**
     * @throws RepositoryException
     */
    public function activateUser(int $userId): void
    {
        $this->updateUser($userId, [
            'ACTIVE' => 'Y',
        ]);
    }

    public function updateToken(int $userId, string $token, DateTime $expiresAt): void
    {
        $this->updateUser($userId, [
            'UF_TOKEN' => $token,
            'UF_TOKEN_EXPIRES_AT' => $expiresAt->toString(),
        ]);
    }

    /**
     * @throws RepositoryException
     */
    public function clearToken(int $userId): void
    {
        $this->updateUser($userId, [
            'UF_TOKEN' => '',
            'UF_TOKEN_EXPIRES_AT' => false,
        ]);
    }

    /**
     * @param array<string, bool|string> $fields
     *
     * @throws RepositoryException
     */
    private function updateUser(int $userId, array $fields): void
    {
        $user = new \CUser();

        if (false === $user->Update($userId, $fields)) {
            throw new RepositoryException((string)$user->LAST_ERROR);
        }
    }
}
