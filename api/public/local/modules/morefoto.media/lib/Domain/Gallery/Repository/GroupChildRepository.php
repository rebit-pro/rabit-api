<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Gallery\Exception\GalleryStorageException;

/** Дети групп с первым готовым кадром: подзапрос по `ux_mf_photo_assignment_sequence` на ребёнка, без чтения всех кадров. */
final readonly class GroupChildRepository
{
    private const string SELECT = "SELECT c.ID,c.PUBLIC_ID,c.GROUP_ID,c.CODE,
        (SELECT p.UF_PUBLIC_ID FROM mf_photo_assignment a INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
            WHERE a.CHILD_ID=c.ID AND p.UF_GROUP_ID=c.GROUP_ID AND p.UF_SHOOT_ID=c.SHOOT_ID AND p.UF_STATUS='ready'
            ORDER BY a.SEQUENCE_NO LIMIT 1) AS PHOTO_ID
        FROM mf_media_child c";

    /** @param non-empty-list<int> $groupIds */
    public function children(array $groupIds): Result
    {
        return $this->query(self::SELECT . ' WHERE c.GROUP_ID IN (' . implode(',', $groupIds) . ')
            ORDER BY c.GROUP_ID,CHAR_LENGTH(c.CODE),c.CODE');
    }

    /** @param non-empty-list<int> $groupIds */
    public function counts(array $groupIds): Result
    {
        return $this->query('SELECT GROUP_ID,COUNT(*) AS CHILDREN FROM mf_media_child
            WHERE GROUP_ID IN (' . implode(',', $groupIds) . ') GROUP BY GROUP_ID');
    }

    /** @return null|array{ID: int|string, PUBLIC_ID: string, GROUP_ID: int|string, CODE: string, PHOTO_ID: null|string} */
    public function find(string $childId): ?array
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $childId)) {
            return null;
        }
        $row = $this->query(self::SELECT . " WHERE c.PUBLIC_ID='{$childId}'")->fetch();

        return false === $row ? null : $row;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read group children.', 0, $error);
        }
    }
}
