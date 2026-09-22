<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Transfer\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Photo\Exception\MediaStorageException;

/** SQL переноса полных наборов детей; изменения выполняются только внутри транзакции вызывающего. */
final readonly class ChildTransferRepository
{
    public function childId(int $shootId, int $groupId, string $code): ?int
    {
        $row = $this->query("SELECT ID FROM mf_media_child WHERE SHOOT_ID={$shootId} AND GROUP_ID={$groupId} AND CODE=" . $this->quote($code) . ' LIMIT 1')->fetch();

        return false === $row ? null : (int)$row['ID'];
    }

    /**
     * @param non-empty-list<int> $childIds
     *
     * @return array<int, array{
     *     publicId: string,
     *     groupId: int,
     *     code: string,
     * }>
     */
    public function children(int $shootId, array $childIds, bool $lock): array
    {
        $result = $this->query('SELECT ID,PUBLIC_ID,GROUP_ID,CODE FROM mf_media_child WHERE SHOOT_ID=' . $shootId
            . ' AND ID IN (' . $this->ids($childIds) . ') ORDER BY ID' . ($lock ? ' FOR UPDATE' : ''));
        $children = [];
        while (false !== ($row = $result->fetch())) {
            $children[(int)$row['ID']] = ['publicId' => (string)$row['PUBLIC_ID'], 'groupId' => (int)$row['GROUP_ID'], 'code' => (string)$row['CODE']];
        }

        return $children;
    }

    /**
     * Назначенные кадры детей по номеру кадра.
     *
     * @param non-empty-list<int> $childIds
     *
     * @return list<array{
     *     childId: int,
     *     sequence: int,
     *     photoId: int,
     *     publicId: string,
     *     revision: int,
     *     status: string,
     * }>
     */
    public function assignments(array $childIds): array
    {
        $result = $this->query('SELECT a.CHILD_ID,a.SEQUENCE_NO,p.ID,p.UF_PUBLIC_ID,p.UF_REVISION,p.UF_STATUS FROM mf_photo_assignment a '
            . 'INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID WHERE a.CHILD_ID IN (' . $this->ids($childIds) . ') ORDER BY a.CHILD_ID,a.SEQUENCE_NO,p.ID');
        $assignments = [];
        while (false !== ($row = $result->fetch())) {
            $assignments[] = [
                'childId' => (int)$row['CHILD_ID'],
                'sequence' => (int)$row['SEQUENCE_NO'],
                'photoId' => (int)$row['ID'],
                'publicId' => (string)$row['UF_PUBLIC_ID'],
                'revision' => (int)$row['UF_REVISION'],
                'status' => (string)$row['UF_STATUS'],
            ];
        }

        return $assignments;
    }

    /**
     * Все дети, которым назначены кадры.
     *
     * @param non-empty-list<int> $photoIds
     *
     * @return array<int, list<int>> ID кадра => ID детей
     */
    public function photoChildren(array $photoIds): array
    {
        $result = $this->query('SELECT PHOTO_ID,CHILD_ID FROM mf_photo_assignment WHERE PHOTO_ID IN (' . $this->ids($photoIds) . ') ORDER BY PHOTO_ID,CHILD_ID');
        $children = [];
        while (false !== ($row = $result->fetch())) {
            $children[(int)$row['PHOTO_ID']][] = (int)$row['CHILD_ID'];
        }

        return $children;
    }

    /** @return list<string> */
    public function codes(int $groupId): array
    {
        $result = $this->query('SELECT CODE FROM mf_media_child WHERE GROUP_ID=' . $groupId);
        $codes = [];
        while (false !== ($row = $result->fetch())) {
            $codes[] = (string)$row['CODE'];
        }

        return $codes;
    }

    public function moveChild(int $childId, int $groupId, string $code): void
    {
        $this->execute("UPDATE mf_media_child SET GROUP_ID={$groupId},CODE=" . $this->quote($code) . ",REVISION=REVISION+1,UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$childId}");
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new MediaStorageException('The transferred child row is missing.');
        }
    }

    /** @param non-empty-list<int> $photoIds */
    public function movePhotos(int $shootId, array $photoIds, int $groupId): void
    {
        $this->execute("UPDATE b_hlbd_mf_photo SET UF_GROUP_ID={$groupId},UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() "
            . "WHERE UF_SHOOT_ID={$shootId} AND ID IN (" . $this->ids($photoIds) . ')');
        if (count($photoIds) !== Application::getConnection()->getAffectedRowsCount()) {
            throw new MediaStorageException('Transferred photos changed during the transfer.');
        }
    }

    public function cover(int $groupId): ?int
    {
        $row = $this->query('SELECT PHOTO_ID FROM mf_media_group_cover WHERE GROUP_ID=' . $groupId . ' FOR UPDATE')->fetch();

        return false === $row ? null : (int)$row['PHOTO_ID'];
    }

    /** Первый назначенный готовый кадр группы: A…Z, затем AA…, по номеру кадра. */
    public function coverCandidate(int $groupId): ?int
    {
        $row = $this->query("SELECT p.ID FROM mf_photo_assignment a INNER JOIN mf_media_child c ON c.ID=a.CHILD_ID
            INNER JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID
            WHERE c.GROUP_ID={$groupId} AND p.UF_GROUP_ID={$groupId} AND p.UF_STATUS='ready'
            ORDER BY CHAR_LENGTH(c.CODE),c.CODE,a.SEQUENCE_NO,p.ID LIMIT 1")->fetch();

        return false === $row ? null : (int)$row['ID'];
    }

    public function replaceCover(int $groupId, ?int $photoId): void
    {
        $this->execute(null === $photoId
            ? 'DELETE FROM mf_media_group_cover WHERE GROUP_ID=' . $groupId
            : "INSERT INTO mf_media_group_cover(GROUP_ID,PHOTO_ID,UPDATED_AT) VALUES({$groupId},{$photoId},UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE PHOTO_ID=VALUES(PHOTO_ID),UPDATED_AT=UTC_TIMESTAMP()");
    }

    /** @param non-empty-list<int> $ids */
    private function ids(array $ids): string
    {
        return implode(',', array_map(static fn(int $id): string => (string)$id, $ids));
    }

    private function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot read child transfer state.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new MediaStorageException('Cannot update child transfer state.', 0, $error);
        }
    }
}
