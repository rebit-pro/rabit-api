<?php

declare(strict_types=1);

namespace Rebit\Auth\Domain\Registration\Repository;

use Bitrix\Main\Application;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Bitrix\Main\Type\DateTime;
use Rebit\Auth\Domain\Registration\Entity\RegistrationConfirmation;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

final readonly class RegistrationConfirmationRepository
{
    use RepositoryExceptionTrait;

    private const string TABLE_NAME = 'rebit_auth_registration_confirmation';

    public function __construct(private ClockInterface $clock) {}

    /**
     * @throws RepositoryException
     */
    public function findByEmail(string $email, bool $forUpdate = false): ?RegistrationConfirmation
    {
        return $this->query(function() use ($email, $forUpdate): ?RegistrationConfirmation {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $emailSql = $helper->forSql($email);

            // Keep SQL DATETIME raw: fetch() localizes it through the Bitrix culture.
            /** @var array{
             *     ID: int|string,
             *     UF_USER_ID: int|string,
             *     UF_EMAIL: string,
             *     UF_CODE_HASH: string,
             *     UF_CODE_EXPIRES_AT: string,
             *     UF_RESEND_AVAILABLE_AT: string,
             *     UF_ATTEMPTS: int|string,
             *     UF_CONFIRMED_AT: null|string,
             *     UF_CREATED_AT: string,
             *     UF_UPDATED_AT: string,
             * }|false $row
             */
            $row = $connection->query(
                sprintf(
                    'SELECT ID, UF_USER_ID, UF_EMAIL, UF_CODE_HASH, UF_CODE_EXPIRES_AT, UF_RESEND_AVAILABLE_AT, UF_ATTEMPTS, UF_CONFIRMED_AT, UF_CREATED_AT, UF_UPDATED_AT FROM %s WHERE UF_EMAIL = \'%s\' LIMIT 1%s',
                    self::TABLE_NAME,
                    $emailSql,
                    $forUpdate ? ' FOR UPDATE' : '',
                ),
            )->fetchRaw();

            if (false === $row) {
                return null;
            }

            $confirmedAt = null;
            if (null !== $row['UF_CONFIRMED_AT'] && '' !== (string)$row['UF_CONFIRMED_AT']) {
                $confirmedAt = new DateTime((string)$row['UF_CONFIRMED_AT'], 'Y-m-d H:i:s');
            }

            return new RegistrationConfirmation(
                id: (int)$row['ID'],
                userId: (int)$row['UF_USER_ID'],
                email: (string)$row['UF_EMAIL'],
                codeHash: (string)$row['UF_CODE_HASH'],
                codeExpiresAt: new DateTime((string)$row['UF_CODE_EXPIRES_AT'], 'Y-m-d H:i:s'),
                resendAvailableAt: new DateTime((string)$row['UF_RESEND_AVAILABLE_AT'], 'Y-m-d H:i:s'),
                attempts: (int)$row['UF_ATTEMPTS'],
                confirmedAt: $confirmedAt,
                createdAt: new DateTime((string)$row['UF_CREATED_AT'], 'Y-m-d H:i:s'),
                updatedAt: new DateTime((string)$row['UF_UPDATED_AT'], 'Y-m-d H:i:s'),
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function create(
        int $userId,
        string $email,
        string $codeHash,
        DateTime $codeExpiresAt,
        DateTime $resendAvailableAt,
    ): void {
        $this->query(function() use ($userId, $email, $codeHash, $codeExpiresAt, $resendAvailableAt): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $now = DateTime::createFromTimestamp($this->clock->now());

            $connection->queryExecute(
                sprintf(
                    'INSERT INTO %s (UF_USER_ID, UF_EMAIL, UF_CODE_HASH, UF_CODE_EXPIRES_AT, UF_RESEND_AVAILABLE_AT, UF_ATTEMPTS, UF_CONFIRMED_AT, UF_CREATED_AT, UF_UPDATED_AT) VALUES (%d, \'%s\', \'%s\', \'%s\', \'%s\', 0, NULL, \'%s\', \'%s\')',
                    self::TABLE_NAME,
                    $userId,
                    $helper->forSql($email),
                    $helper->forSql($codeHash),
                    $helper->forSql($this->formatDateTime($codeExpiresAt)),
                    $helper->forSql($this->formatDateTime($resendAvailableAt)),
                    $helper->forSql($this->formatDateTime($now)),
                    $helper->forSql($this->formatDateTime($now)),
                ),
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function updateForResend(
        int $id,
        int $userId,
        string $codeHash,
        DateTime $codeExpiresAt,
        DateTime $resendAvailableAt,
    ): void {
        $this->query(function() use ($id, $userId, $codeHash, $codeExpiresAt, $resendAvailableAt): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();

            $connection->queryExecute(
                sprintf(
                    'UPDATE %s SET UF_USER_ID = %d, UF_CODE_HASH = \'%s\', UF_CODE_EXPIRES_AT = \'%s\', UF_RESEND_AVAILABLE_AT = \'%s\', UF_ATTEMPTS = 0, UF_CONFIRMED_AT = NULL, UF_UPDATED_AT = \'%s\' WHERE ID = %d',
                    self::TABLE_NAME,
                    $userId,
                    $helper->forSql($codeHash),
                    $helper->forSql($this->formatDateTime($codeExpiresAt)),
                    $helper->forSql($this->formatDateTime($resendAvailableAt)),
                    $helper->forSql($this->formatDateTime(DateTime::createFromTimestamp($this->clock->now()))),
                    $id,
                ),
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function incrementAttempts(int $id): void
    {
        $this->query(function() use ($id): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();

            $connection->queryExecute(
                sprintf(
                    'UPDATE %s SET UF_ATTEMPTS = UF_ATTEMPTS + 1, UF_UPDATED_AT = \'%s\' WHERE ID = %d',
                    self::TABLE_NAME,
                    $helper->forSql($this->formatDateTime(DateTime::createFromTimestamp($this->clock->now()))),
                    $id,
                ),
            );
        });
    }

    /**
     * @throws RepositoryException
     */
    public function markConfirmed(int $id): void
    {
        $this->query(function() use ($id): void {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $confirmedAt = $this->formatDateTime(DateTime::createFromTimestamp($this->clock->now()));

            $connection->queryExecute(
                sprintf(
                    'UPDATE %s SET UF_CONFIRMED_AT = \'%s\', UF_UPDATED_AT = \'%s\' WHERE ID = %d',
                    self::TABLE_NAME,
                    $helper->forSql($confirmedAt),
                    $helper->forSql($confirmedAt),
                    $id,
                ),
            );
        });
    }

    private function formatDateTime(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
