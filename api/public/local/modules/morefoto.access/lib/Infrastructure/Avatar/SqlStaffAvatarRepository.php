<?php

declare(strict_types=1);

namespace Morefoto\Access\Infrastructure\Avatar;

use Bitrix\Main\Application;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Domain\Avatar\Entity\StaffAvatar;
use Morefoto\Access\Domain\Staff\Exception\AccessStorageException;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class SqlStaffAvatarRepository implements StaffAvatarRepositoryInterface
{
    private const string COLUMNS = 'USER_ID,VERSION,FINGERPRINT,MIME,BYTES,WIDTH,HEIGHT';

    public function find(int $userId): ?StaffAvatar
    {
        try {
            $row = Application::getConnection()
                ->query(sprintf('SELECT %s FROM mf_staff_avatar WHERE USER_ID=%d', self::COLUMNS, $userId))
                ->fetch()
            ;
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot read staff avatar.', 0, $exception);
        }

        return false === $row ? null : self::entity($row);
    }

    public function locked(int $userId, callable $operation): mixed
    {
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            // The staff profile row always exists for an employee, so it serializes the first upload as well.
            if (false === $connection->query(sprintf('SELECT ID FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID=%d FOR UPDATE', $userId))->fetch()) {
                throw new HttpException('STAFF_NOT_FOUND', 404);
            }
            $row = $connection->query(sprintf('SELECT %s FROM mf_staff_avatar WHERE USER_ID=%d FOR UPDATE', self::COLUMNS, $userId))->fetch();
            $result = $operation(false === $row ? null : self::entity($row));
            $connection->commitTransaction();

            return $result;
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }
    }

    public function save(StaffAvatar $avatar, int $updatedBy): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $values = sprintf(
            "VERSION=%d,FINGERPRINT='%s',MIME='%s',BYTES=%d,WIDTH=%d,HEIGHT=%d,UPDATED_BY=%d,UPDATED_AT=UTC_TIMESTAMP()",
            $avatar->version,
            $helper->forSql($avatar->fingerprint),
            $helper->forSql($avatar->mimeType),
            $avatar->bytes,
            $avatar->width,
            $avatar->height,
            $updatedBy,
        );
        try {
            $connection->queryExecute(sprintf(
                'INSERT INTO mf_staff_avatar SET USER_ID=%d,%s ON DUPLICATE KEY UPDATE %s',
                $avatar->userId,
                $values,
                $values,
            ));
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot save staff avatar.', 0, $exception);
        }
    }

    public function delete(int $userId): void
    {
        try {
            Application::getConnection()->queryExecute(sprintf('DELETE FROM mf_staff_avatar WHERE USER_ID=%d', $userId));
        } catch (\Throwable $exception) {
            throw new AccessStorageException('Cannot delete staff avatar.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private static function entity(array $row): StaffAvatar
    {
        return new StaffAvatar(
            userId: (int)$row['USER_ID'],
            version: (int)$row['VERSION'],
            fingerprint: (string)$row['FINGERPRINT'],
            mimeType: (string)$row['MIME'],
            bytes: (int)$row['BYTES'],
            width: (int)$row['WIDTH'],
            height: (int)$row['HEIGHT'],
        );
    }
}
