<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\User\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Domain\User\Entity\UserCredentials;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Entity\UserToken;
use Rebit\Auth\Domain\User\Service\TokenExpirationParser;
use Rebit\Share\Application\Contract\Auth\TokenRevokerInterface;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

final readonly class UserRepository implements LoginUserRepositoryInterface, TokenRevokerInterface
{
    use RepositoryExceptionTrait;

    /**
     * @throws RepositoryException
     */
    public function findByToken(string $token): ?UserToken
    {
        if ('' === $token) {
            return null;
        }

        return $this->query(static function() use ($token): ?UserToken {
            $row = UserTable::query()
                ->setSelect(['ID', 'UF_TOKEN', 'UF_TOKEN_EXPIRES_AT', 'UF_AUTH_REGISTRATION_PENDING'])
                ->where('UF_TOKEN', $token)
                ->where('ACTIVE', 'Y')
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row || 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0)) {
                return null;
            }

            if (!hash_equals((string)$row['UF_TOKEN'], $token)) {
                return null;
            }

            return new UserToken(
                userId: (int)$row['ID'],
                expiresAt: TokenExpirationParser::parse($row['UF_TOKEN_EXPIRES_AT']),
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
                ->setSelect(['ID', 'PASSWORD', 'EMAIL', 'NAME', 'UF_AUTH_REGISTRATION_PENDING'])
                ->enablePrivateFields()
                ->where('EMAIL', $email)
                ->where('ACTIVE', 'Y')
                ->setLimit(1)
                ->exec()
                ->fetch()
            ;

            if (false === $row || 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0)) {
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

    /** Current read serialized with privileged identity changes and session revocation. */
    public function findActiveByEmailForUpdate(string $email): ?UserCredentials
    {
        return $this->query(static function() use ($email): ?UserCredentials {
            $connection = Application::getConnection();
            /** @var array{
             *     ID: int|string,
             *     PASSWORD: string,
             *     EMAIL: string,
             *     NAME: null|string,
             *     UF_AUTH_REGISTRATION_PENDING: null|int|string,
             * }|false $row */
            $row = $connection->query(sprintf(
                "SELECT u.ID, u.PASSWORD, u.EMAIL, u.NAME, uf.UF_AUTH_REGISTRATION_PENDING FROM b_user u LEFT JOIN b_uts_user uf ON uf.VALUE_ID = u.ID WHERE u.EMAIL = '%s' AND u.ACTIVE = 'Y' LIMIT 1 FOR UPDATE",
                $connection->getSqlHelper()->forSql($email),
            ))->fetch();
            if (false === $row || 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0)) {
                return null;
            }

            return new UserCredentials(
                id: (int)$row['ID'],
                passwordHash: $row['PASSWORD'],
                email: $row['EMAIL'],
                name: (string)$row['NAME'],
            );
        });
    }

    /** Address lookup serialized with staff provisioning and contact changes. */
    public function findByEmailForUpdate(string $email): ?UserRegistrationState
    {
        return $this->query(static function() use ($email): ?UserRegistrationState {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            /** @var array{
             *     ID: int|string,
             *     EMAIL: string,
             *     NAME: null|string,
             *     ACTIVE: string,
             *     UF_AUTH_REGISTRATION_PENDING: null|int|string,
             * }|false $row */
            $row = $connection->query(sprintf(
                "SELECT u.ID,u.EMAIL,u.NAME,u.ACTIVE,uf.UF_AUTH_REGISTRATION_PENDING FROM b_user u LEFT JOIN b_uts_user uf ON uf.VALUE_ID=u.ID WHERE LOWER(u.EMAIL)=LOWER('%s') LIMIT 1 FOR UPDATE",
                $helper->forSql(trim($email)),
            ))->fetch();
            if (false === $row) {
                return null;
            }

            return new UserRegistrationState(
                id: (int)$row['ID'],
                email: (string)$row['EMAIL'],
                name: (string)$row['NAME'],
                isActive: 'Y' === (string)$row['ACTIVE'],
                isPendingRegistration: 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0),
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
                ->setSelect(['ID', 'EMAIL', 'NAME', 'ACTIVE', 'UF_AUTH_REGISTRATION_PENDING'])
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
                isPendingRegistration: 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0),
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
                ->setSelect(['ID', 'EMAIL', 'NAME', 'ACTIVE', 'UF_AUTH_REGISTRATION_PENDING'])
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
                isPendingRegistration: 1 === (int)($row['UF_AUTH_REGISTRATION_PENDING'] ?? 0),
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
            'UF_AUTH_REGISTRATION_PENDING' => 1,
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
            'UF_AUTH_REGISTRATION_PENDING' => 0,
        ]);
    }

    /**
     * @throws RepositoryException
     */
    public function changePassword(int $userId, string $password): void
    {
        $this->updateUser($userId, [
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
        ]);
    }

    public function updateToken(int $userId, string $token, DateTime $expiresAt): void
    {
        // Token fields belong to Auth. CUser::Update opens a nested transaction and
        // can leave it open on a storage exception, preventing the caller rollback.
        $this->query(static function() use ($userId, $token, $expiresAt): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $connection->queryExecute(sprintf(
                "INSERT INTO b_uts_user (VALUE_ID, UF_TOKEN, UF_TOKEN_EXPIRES_AT) VALUES (%d, '%s', '%s') ON DUPLICATE KEY UPDATE UF_TOKEN = VALUES(UF_TOKEN), UF_TOKEN_EXPIRES_AT = VALUES(UF_TOKEN_EXPIRES_AT)",
                $userId,
                $helper->forSql($token),
                $helper->forSql(TokenExpirationParser::format($expiresAt)),
            ));
        });
    }

    /**
     * @throws RepositoryException
     */
    public function clearToken(int $userId): void
    {
        // Keep revocation in the caller's transaction. CUser::Update starts a nested
        // transaction whose rollback can hide the storage error and leave outer work open.
        $this->query(static function() use ($userId): void {
            Application::getConnection()->queryExecute(sprintf(
                "UPDATE b_uts_user SET UF_TOKEN = '', UF_TOKEN_EXPIRES_AT = NULL WHERE VALUE_ID = %d",
                $userId,
            ));
        });
    }

    /** Current read: plain ORM re-read could retain a stale REPEATABLE READ snapshot. */
    public function findByIdForUpdate(int $userId): ?UserRegistrationState
    {
        return $this->query(static function() use ($userId): ?UserRegistrationState {
            /** @var array{
             *     ID: int|string,
             *     EMAIL: string,
             *     NAME: null|string,
             *     ACTIVE: string,
             *     UF_AUTH_REGISTRATION_PENDING: null|int|string,
             * }|false $row */
            $row = Application::getConnection()->query(sprintf(
                'SELECT u.ID, u.EMAIL, u.NAME, u.ACTIVE, uf.UF_AUTH_REGISTRATION_PENDING FROM b_user u LEFT JOIN b_uts_user uf ON uf.VALUE_ID = u.ID WHERE u.ID = %d FOR UPDATE',
                $userId,
            ))->fetch();
            if (false === $row) {
                return null;
            }

            return new UserRegistrationState(
                id: (int)$row['ID'],
                email: $row['EMAIL'],
                name: (string)$row['NAME'],
                isActive: 'Y' === $row['ACTIVE'],
                isPendingRegistration: 1 === (int)$row['UF_AUTH_REGISTRATION_PENDING'],
            );
        });
    }

    public function revokeToken(int $userId, string $token): void
    {
        if ('' === $token) {
            return;
        }
        $this->query(static function() use ($userId, $token): void {
            $connection = Application::getConnection();
            $connection->queryExecute(sprintf(
                "UPDATE b_uts_user SET UF_TOKEN = '', UF_TOKEN_EXPIRES_AT = NULL WHERE VALUE_ID = %d AND BINARY UF_TOKEN = '%s'",
                $userId,
                $connection->getSqlHelper()->forSql($token),
            ));
        });
    }

    /**
     * Returns the identity to the pending state without a session: login needs an active user, and the next
     * invitation sets a new password. Plain SQL keeps the change in the caller's transaction, as clearToken() does.
     *
     * @throws RepositoryException
     */
    public function resetToPending(int $userId): void
    {
        $this->query(static function() use ($userId): void {
            $connection = Application::getConnection();
            $connection->queryExecute(sprintf("UPDATE b_user SET ACTIVE='N',TIMESTAMP_X=UTC_TIMESTAMP() WHERE ID=%d", $userId));
            if (1 !== $connection->getAffectedRowsCount()) {
                throw new RepositoryException('Staff Auth identity was not reset.');
            }
            $connection->queryExecute(sprintf(
                "INSERT INTO b_uts_user (VALUE_ID, UF_AUTH_REGISTRATION_PENDING, UF_TOKEN, UF_TOKEN_EXPIRES_AT) VALUES (%d, 1, '', NULL) "
                . "ON DUPLICATE KEY UPDATE UF_AUTH_REGISTRATION_PENDING = 1, UF_TOKEN = '', UF_TOKEN_EXPIRES_AT = NULL",
                $userId,
            ));
        });
    }

    public function updateStaffContact(int $userId, string $email, string $name): void
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        if (1 > $userId || false === filter_var($email, FILTER_VALIDATE_EMAIL) || '' === $name) {
            throw new \InvalidArgumentException('Valid staff identity fields are required.');
        }
        $this->query(static function() use ($userId, $email, $name): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $connection->queryExecute(sprintf(
                "UPDATE b_user SET LOGIN='%s',EMAIL='%s',NAME='%s',TIMESTAMP_X=UTC_TIMESTAMP() WHERE ID=%d",
                $helper->forSql($email),
                $helper->forSql($email),
                $helper->forSql($name),
                $userId,
            ));
            if (1 !== $connection->getAffectedRowsCount()) {
                throw new RepositoryException('Staff Auth identity was not updated.');
            }
        });
    }

    /**
     * @param array<string, bool|int|string> $fields
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
