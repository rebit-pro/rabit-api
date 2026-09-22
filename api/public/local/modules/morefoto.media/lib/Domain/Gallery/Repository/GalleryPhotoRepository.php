<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Gallery\Exception\GalleryStorageException;

final readonly class GalleryPhotoRepository
{
    private const string READY = "SELECT a.PUBLIC_ID AS ASSIGNMENT_ID,c.PUBLIC_ID AS CHILD_ID,c.ID AS NATIVE_CHILD_ID,c.CODE,a.SEQUENCE_NO,
        p.UF_PUBLIC_ID AS PHOTO_ID,p.UF_WIDTH,p.UF_HEIGHT,p.UF_REVISION
        FROM mf_photo_assignment a INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID
        INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
        WHERE c.GROUP_ID=%1\$d AND c.SHOOT_ID=%2\$d
        AND p.UF_GROUP_ID=%1\$d AND p.UF_SHOOT_ID=%2\$d AND p.UF_STATUS='ready'%3\$s
        ORDER BY c.ID,a.SEQUENCE_NO LIMIT 5001";

    public function list(int $groupId, int $shootId): Result
    {
        try {
            return Application::getConnection()->query(sprintf(self::READY, $groupId, $shootId, ''));
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read gallery assignments.', 0, $error);
        }
    }

    /** @param non-empty-list<int> $childIds */
    public function children(int $groupId, int $shootId, array $childIds): Result
    {
        try {
            return Application::getConnection()->query(sprintf(self::READY, $groupId, $shootId, ' AND c.ID IN (' . implode(',', $childIds) . ')'));
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read child photos.', 0, $error);
        }
    }

    public function photoId(int $groupId, int $shootId, string $assignmentId): ?string
    {
        try {
            $id = Application::getConnection()->getSqlHelper()->forSql($assignmentId);
            $row = Application::getConnection()->query("SELECT p.UF_PUBLIC_ID
                FROM mf_photo_assignment a INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID
                INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
                WHERE a.PUBLIC_ID='{$id}' AND c.GROUP_ID={$groupId} AND c.SHOOT_ID={$shootId}
                AND p.UF_GROUP_ID={$groupId} AND p.UF_SHOOT_ID={$shootId} AND p.UF_STATUS='ready'")->fetch();

            return false === $row ? null : (string)$row['UF_PUBLIC_ID'];
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot resolve preview assignment.', 0, $error);
        }
    }
}
