<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Access;

use Bitrix\Main\Application;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Share\Shared\Exception\RepositoryException;
use Rebit\Share\Shared\Repository\RepositoryExceptionTrait;

final readonly class SqlAccessLinkRepository implements AccessLinkRepositoryInterface
{
    use RepositoryExceptionTrait;

    private const string TABLE = 'rebit_auth_access_link';
    private const string COLUMNS = 'ID, USER_ID, PURPOSE, TOKEN_HASH, ISSUED_AT, EXPIRES_AT, RESEND_AVAILABLE_AT, USED_AT, ISSUED_BY';

    /**
     * @throws RepositoryException
     */
    public function findByTokenHash(string $tokenHash): ?AccessLink
    {
        return $this->one(sprintf("WHERE TOKEN_HASH = '%s' LIMIT 1", $this->sql($tokenHash)));
    }

    /**
     * @throws RepositoryException
     */
    public function findByTokenHashForUpdate(string $tokenHash): ?AccessLink
    {
        return $this->one(sprintf("WHERE TOKEN_HASH = '%s' LIMIT 1 FOR UPDATE", $this->sql($tokenHash)));
    }

    /**
     * @throws RepositoryException
     */
    public function findForUserForUpdate(int $userId, AccessLinkPurposeEnum $purpose): ?AccessLink
    {
        return $this->one(sprintf("WHERE USER_ID = %d AND PURPOSE = '%s' LIMIT 1 FOR UPDATE", $userId, $purpose->value));
    }

    /**
     * @throws RepositoryException
     */
    public function replace(
        int $userId,
        AccessLinkPurposeEnum $purpose,
        string $tokenHash,
        int $issuedAt,
        int $expiresAt,
        int $resendAvailableAt,
        ?int $issuedBy,
    ): void {
        $this->query(function() use ($userId, $purpose, $tokenHash, $issuedAt, $expiresAt, $resendAvailableAt, $issuedBy): void {
            Application::getConnection()->queryExecute(sprintf(
                "INSERT INTO %s (USER_ID, PURPOSE, TOKEN_HASH, ISSUED_AT, EXPIRES_AT, RESEND_AVAILABLE_AT, USED_AT, ISSUED_BY) VALUES (%d, '%s', '%s', '%s', '%s', '%s', NULL, %s) "
                . 'ON DUPLICATE KEY UPDATE TOKEN_HASH = VALUES(TOKEN_HASH), ISSUED_AT = VALUES(ISSUED_AT), EXPIRES_AT = VALUES(EXPIRES_AT), '
                . 'RESEND_AVAILABLE_AT = VALUES(RESEND_AVAILABLE_AT), USED_AT = NULL, ISSUED_BY = VALUES(ISSUED_BY)',
                self::TABLE,
                $userId,
                $purpose->value,
                $this->sql($tokenHash),
                self::datetime($issuedAt),
                self::datetime($expiresAt),
                self::datetime($resendAvailableAt),
                null === $issuedBy ? 'NULL' : (string)$issuedBy,
            ));
        });
    }

    /**
     * @throws RepositoryException
     */
    public function markUsed(int $id, int $usedAt): void
    {
        $this->query(static function() use ($id, $usedAt): void {
            Application::getConnection()->queryExecute(sprintf(
                "UPDATE %s SET USED_AT = '%s' WHERE ID = %d",
                self::TABLE,
                self::datetime($usedAt),
                $id,
            ));
        });
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, AccessLink>
     *
     * @throws RepositoryException
     */
    public function invitationsFor(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        return $this->query(function() use ($userIds): array {
            $result = Application::getConnection()->query(sprintf(
                "SELECT %s FROM %s WHERE PURPOSE = '%s' AND USER_ID IN (%s)",
                self::COLUMNS,
                self::TABLE,
                AccessLinkPurposeEnum::INVITE->value,
                implode(',', array_map(intval(...), $userIds)),
            ));
            $links = [];
            while (false !== ($row = $result->fetchRaw())) {
                $link = $this->link($row);
                $links[$link->userId] = $link;
            }

            return $links;
        });
    }

    /**
     * @throws RepositoryException
     */
    private function one(string $condition): ?AccessLink
    {
        return $this->query(function() use ($condition): ?AccessLink {
            // Raw fetch keeps DATETIME in SQL format instead of the Bitrix culture format.
            $row = Application::getConnection()->query(
                sprintf('SELECT %s FROM %s %s', self::COLUMNS, self::TABLE, $condition),
            )->fetchRaw();

            return false === $row ? null : $this->link($row);
        });
    }

    /**
     * @param array{
     *     ID: int|string,
     *     USER_ID: int|string,
     *     PURPOSE: string,
     *     TOKEN_HASH: string,
     *     ISSUED_AT: string,
     *     EXPIRES_AT: string,
     *     RESEND_AVAILABLE_AT: string,
     *     USED_AT: null|string,
     *     ISSUED_BY: null|int|string,
     * } $row
     */
    private function link(array $row): AccessLink
    {
        return new AccessLink(
            id: (int)$row['ID'],
            userId: (int)$row['USER_ID'],
            purpose: AccessLinkPurposeEnum::from((string)$row['PURPOSE']),
            tokenHash: (string)$row['TOKEN_HASH'],
            issuedAt: self::timestamp((string)$row['ISSUED_AT']),
            expiresAt: self::timestamp((string)$row['EXPIRES_AT']),
            resendAvailableAt: self::timestamp((string)$row['RESEND_AVAILABLE_AT']),
            usedAt: null === $row['USED_AT'] || '' === (string)$row['USED_AT'] ? null : self::timestamp((string)$row['USED_AT']),
            issuedBy: null === $row['ISSUED_BY'] ? null : (int)$row['ISSUED_BY'],
        );
    }

    private function sql(string $value): string
    {
        return Application::getConnection()->getSqlHelper()->forSql($value);
    }

    /** Dates are stored in UTC regardless of the server time zone. */
    private static function datetime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function timestamp(string $datetime): int
    {
        return (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->getTimestamp();
    }
}
