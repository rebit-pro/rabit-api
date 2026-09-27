<?php

declare(strict_types=1);

namespace Morefoto\Media\Infrastructure\Organization;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Morefoto\Media\Application\Photo\Service\PhotoFileCleaner;
use Rebit\Share\Contracts\Media\Dto\RemovedMediaFilesDto;
use Rebit\Share\Contracts\Media\StructureMediaRemovalInterface;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Photos, children, covers and gallery keys of the removed structure. Every foreign key is RESTRICT, so rows go
 * children first: duplicates before their originals, assignments before photos and children.
 */
final readonly class StructureMediaRemoval implements StructureMediaRemovalInterface
{
    public function __construct(private PhotoFileCleaner $files) {}

    public function remove(StructureRemovalDto $removal): RemovedMediaFilesDto
    {
        if ([] === $removal->groupIds && [] === $removal->shootIds) {
            return new RemovedMediaFilesDto([]);
        }
        $connection = Application::getConnection();
        // Native IDs read by Organization under its locks: integers only, safe to inline.
        $groups = [] === $removal->groupIds ? '0' : implode(',', $removal->groupIds);
        $shoots = [] === $removal->shootIds ? '0' : implode(',', $removal->shootIds);
        // Uploads, assignments and covers of a shoot serialize on its media revision.
        $affected = $this->ids($connection, "SELECT DISTINCT UF_SHOOT_ID AS ID FROM b_hlbd_mf_group WHERE ID IN ({$groups}) UNION SELECT ID FROM b_hlbd_mf_shoot WHERE ID IN ({$shoots})");
        if ([] !== $affected) {
            $connection->query('SELECT SHOOT_ID FROM mf_media_shoot_state WHERE SHOOT_ID IN (' . implode(',', $affected) . ') ORDER BY SHOOT_ID FOR UPDATE')->fetchAll();
        }
        $result = $connection->query("SELECT ID,UF_PUBLIC_ID,UF_STATUS,UF_ORIGINAL_PATH FROM b_hlbd_mf_photo WHERE UF_GROUP_ID IN ({$groups}) OR UF_SHOOT_ID IN ({$shoots}) FOR UPDATE");
        $photoIds = [];
        $files = [];
        while (false !== ($row = $result->fetch())) {
            if ('processing' === $row['UF_STATUS']) {
                throw new HttpException('PHOTO_PROCESSING', 409);
            }
            $photoIds[] = (int)$row['ID'];
            $files[] = ['photoId' => (string)$row['UF_PUBLIC_ID'], 'originalPath' => null === $row['UF_ORIGINAL_PATH'] ? null : (string)$row['UF_ORIGINAL_PATH']];
        }
        $children = $this->ids($connection, "SELECT ID FROM mf_media_child WHERE GROUP_ID IN ({$groups}) OR SHOOT_ID IN ({$shoots})");
        $photos = [] === $photoIds ? '0' : implode(',', $photoIds);
        // Duplicates of removed photos may live in other groups: they point at a disappearing original.
        $duplicates = "SELECT ID FROM b_hlbd_mf_photo WHERE UF_EXISTING_PHOTO_ID IN ({$photos})";
        $connection->queryExecute("DELETE FROM mf_photo_assignment WHERE PHOTO_ID IN ({$photos}) OR PHOTO_ID IN (SELECT ID FROM ({$duplicates}) d)"
            . ([] === $children ? '' : ' OR CHILD_ID IN (' . implode(',', $children) . ')'));
        $connection->queryExecute("DELETE FROM mf_media_group_cover WHERE GROUP_ID IN ({$groups}) OR PHOTO_ID IN ({$photos}) OR PHOTO_ID IN (SELECT ID FROM ({$duplicates}) d)");
        // A photo moved out of a removed group keeps living in its current group.
        $connection->queryExecute("UPDATE b_hlbd_mf_photo SET UF_ORIGINAL_GROUP_ID=UF_GROUP_ID WHERE UF_ORIGINAL_GROUP_ID IN ({$groups}) AND ID NOT IN ({$photos})");
        $connection->queryExecute("DELETE FROM b_hlbd_mf_photo WHERE UF_EXISTING_PHOTO_ID IN ({$photos})");
        $connection->queryExecute("DELETE FROM b_hlbd_mf_photo WHERE ID IN ({$photos})");
        if ([] !== $children) {
            $connection->queryExecute('DELETE FROM mf_media_child WHERE ID IN (' . implode(',', $children) . ')');
        }
        $connection->queryExecute("DELETE FROM mf_gallery_capability WHERE GROUP_PUBLIC_ID IN (SELECT UF_PUBLIC_ID FROM b_hlbd_mf_group WHERE ID IN ({$groups}))");
        $connection->queryExecute("DELETE FROM mf_media_shoot_state WHERE SHOOT_ID IN ({$shoots})");
        // Editors of a surviving shoot reload: their photo lists lost the removed groups.
        $connection->queryExecute('UPDATE mf_media_shoot_state SET REVISION=REVISION+1,UPDATED_AT=UTC_TIMESTAMP() WHERE SHOOT_ID IN (SELECT ID FROM b_hlbd_mf_shoot WHERE ID IN ('
            . ([] === $affected ? '0' : implode(',', $affected)) . ") AND ID NOT IN ({$shoots}))");

        return new RemovedMediaFilesDto($files);
    }

    public function removeFiles(RemovedMediaFilesDto $files): void
    {
        foreach ($files->photos as $photo) {
            $this->files->remove($photo['photoId'], $photo['originalPath']);
        }
    }

    /** @return list<int> */
    private function ids(Connection $connection, string $sql): array
    {
        $ids = [];
        $result = $connection->query($sql);
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }
}
