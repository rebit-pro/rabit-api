<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Access;

use Bitrix\Main\Application;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Dto\AccessAccountDto;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

/** Bitrix user row as seen by access links; writes go through CUser inside the caller's transaction. */
final readonly class UserAccessAccount implements AccessAccountInterface
{
    use RepositoryExceptionTrait;

    private const string SELECT = 'SELECT u.ID, u.EMAIL, u.NAME, u.ACTIVE, u.PASSWORD, uf.UF_AUTH_REGISTRATION_PENDING '
        . 'FROM b_user u LEFT JOIN b_uts_user uf ON uf.VALUE_ID = u.ID ';

    public function __construct(private UserRepository $users) {}

    /**
     * @throws RepositoryException
     */
    public function findById(int $userId): ?AccessAccountDto
    {
        return $this->one(sprintf('WHERE u.ID = %d LIMIT 1', $userId));
    }

    /**
     * @throws RepositoryException
     */
    public function lockById(int $userId): ?AccessAccountDto
    {
        return $this->one(sprintf('WHERE u.ID = %d LIMIT 1 FOR UPDATE', $userId));
    }

    /**
     * @throws RepositoryException
     */
    public function lockByEmail(string $email): ?AccessAccountDto
    {
        $email = Application::getConnection()->getSqlHelper()->forSql($email);

        return $this->one(sprintf("WHERE LOWER(u.EMAIL) = LOWER('%s') LIMIT 1 FOR UPDATE", $email));
    }

    /**
     * @throws RepositoryException
     */
    public function changePassword(int $userId, string $password): void
    {
        $this->users->changePassword($userId, $password);
    }

    /**
     * @throws RepositoryException
     */
    public function activate(int $userId): void
    {
        $this->users->activateUser($userId);
    }

    /**
     * @throws RepositoryException
     */
    private function one(string $condition): ?AccessAccountDto
    {
        return $this->query(static function() use ($condition): ?AccessAccountDto {
            /** @var array{
             *     ID: int|string,
             *     EMAIL: null|string,
             *     NAME: null|string,
             *     ACTIVE: string,
             *     PASSWORD: null|string,
             *     UF_AUTH_REGISTRATION_PENDING: null|int|string,
             * }|false $row
             */
            $row = Application::getConnection()->query(self::SELECT . $condition)->fetchRaw();

            return false === $row ? null : new AccessAccountDto(
                id: (int)$row['ID'],
                email: (string)$row['EMAIL'],
                name: (string)$row['NAME'],
                active: 'Y' === $row['ACTIVE'],
                pending: 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0),
                passwordHash: (string)$row['PASSWORD'],
            );
        });
    }
}
