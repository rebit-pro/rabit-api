<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Gallery\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Gallery\Exception\GalleryStorageException;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class ShootGalleryRepository
{
    public function current(string $shootId): ?string
    {
        $row = $this->query('SELECT ACCESS_KEY FROM mf_shoot_gallery_link WHERE REVOKED=0 AND SHOOT_PUBLIC_ID=' . $this->quote($shootId))->fetch();

        return false === $row ? null : (string)$row['ACCESS_KEY'];
    }

    public function shoot(string $key): string
    {
        $row = $this->query('SELECT SHOOT_PUBLIC_ID FROM mf_shoot_gallery_link WHERE REVOKED=0 AND ACCESS_KEY=' . $this->quote($key))->fetch();

        return false === $row ? throw new HttpException('GALLERY_NOT_FOUND', 404) : (string)$row['SHOOT_PUBLIC_ID'];
    }

    public function ensure(string $shootId): string
    {
        $id = $this->quote($shootId);
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $key = bin2hex(random_bytes(16));
            try {
                Application::getConnection()->queryExecute("INSERT INTO mf_shoot_gallery_link(SHOOT_PUBLIC_ID,ACCESS_KEY) VALUES({$id},'{$key}') ON DUPLICATE KEY UPDATE REVOKED=REVOKED");
            } catch (\Throwable $error) {
                throw new GalleryStorageException('Cannot store shoot gallery link.', 0, $error);
            }
            $row = $this->query("SELECT ACCESS_KEY,REVOKED FROM mf_shoot_gallery_link WHERE SHOOT_PUBLIC_ID={$id}")->fetch();
            if (false !== $row) {
                if (0 !== (int)$row['REVOKED']) {
                    throw new HttpException('GALLERY_LINK_REVOKED', 409);
                }

                return (string)$row['ACCESS_KEY'];
            }
        }
        throw new GalleryStorageException('Cannot issue a unique shoot gallery link.');
    }

    /** @param list<string> $groupIds
     * @return array<string, ?string> действующий адрес по группе, null у старого ключа без сохранённого адреса
     */
    public function groupLinks(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }
        $ids = implode(',', array_map($this->quote(...), $groupIds));
        $rows = $this->query("SELECT c.GROUP_PUBLIC_ID,COALESCE(a.ALIAS,c.TOKEN) AS LINK_KEY
            FROM mf_gallery_capability c LEFT JOIN mf_gallery_alias a ON a.TOKEN_HASH=c.TOKEN_HASH
            WHERE c.REVOKED=0 AND c.GROUP_PUBLIC_ID IN ({$ids})
            ORDER BY c.TOKEN IS NULL,c.CREATED_AT DESC,c.TOKEN_HASH DESC");
        $keys = [];
        while (false !== ($row = $rows->fetch())) {
            $keys[(string)$row['GROUP_PUBLIC_ID']] ??= null === $row['LINK_KEY'] ? null : (string)$row['LINK_KEY'];
        }

        return $keys;
    }

    /** @param non-empty-list<int> $groupIds
     * @return array<int, string> публичные ID выбранных обложек либо первых доступных кадров
     */
    public function covers(int $shootId, array $groupIds): array
    {
        $ids = implode(',', $groupIds);
        $rows = $this->query("SELECT GROUP_ID,PHOTO_ID FROM (
            SELECT c.GROUP_ID,p.UF_PUBLIC_ID AS PHOTO_ID,
                ROW_NUMBER() OVER (PARTITION BY c.GROUP_ID ORDER BY (cover.PHOTO_ID IS NOT NULL) DESC,c.ID,a.SEQUENCE_NO,p.ID) AS position_no
            FROM mf_media_child c JOIN mf_photo_assignment a ON a.CHILD_ID=c.ID
            JOIN b_hlbd_mf_photo p ON p.ID=a.PHOTO_ID AND p.UF_GROUP_ID=c.GROUP_ID AND p.UF_SHOOT_ID=c.SHOOT_ID
            LEFT JOIN mf_media_group_cover cover ON cover.GROUP_ID=c.GROUP_ID AND cover.PHOTO_ID=p.ID
            WHERE c.SHOOT_ID={$shootId} AND c.GROUP_ID IN ({$ids}) AND p.UF_STATUS='ready'
        ) ranked WHERE position_no=1");
        $photos = [];
        while (false !== ($row = $rows->fetch())) {
            $photos[(int)$row['GROUP_ID']] = (string)$row['PHOTO_ID'];
        }

        return $photos;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new GalleryStorageException('Cannot read shoot gallery.', 0, $error);
        }
    }

    private function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }
}
