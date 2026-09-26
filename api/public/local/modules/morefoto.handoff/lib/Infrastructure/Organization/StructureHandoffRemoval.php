<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Organization;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Handoff\StructureHandoffRemovalInterface;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;

/**
 * Links and staff lists of the removed structure go with it. A staff list of a surviving shoot only loses the rows
 * of removed groups or children, and a transfer into a removed group is forgotten as a whole (the four result columns go together).
 */
final readonly class StructureHandoffRemoval implements StructureHandoffRemovalInterface
{
    public function remove(StructureRemovalDto $removal): void
    {
        $connection = Application::getConnection();
        // Native IDs read by Organization under its locks: integers only, safe to inline.
        $requests = [];
        foreach (['SHOOT_ID' => $removal->shootIds, 'INSTITUTION_ID' => $removal->institutionIds] as $column => $ids) {
            if ([] !== $ids) {
                $requests[] = $column . ' IN (' . implode(',', $ids) . ')';
            }
        }
        $requestIds = [] === $requests ? '' : 'SELECT ID FROM mf_staff_request WHERE ' . implode(' OR ', $requests);
        if ([] !== $removal->groupIds) {
            $groups = implode(',', $removal->groupIds);
            $connection->queryExecute('UPDATE mf_staff_request_row SET TRANSFER_FROM_CODE=NULL,TRANSFER_GROUP_ID=NULL,TRANSFER_CODE=NULL,TRANSFER_PHOTO_IDS_JSON=NULL WHERE TRANSFER_GROUP_ID IN (' . $groups . ')');
            $connection->queryExecute('DELETE FROM mf_staff_request_row WHERE GROUP_ID IN (' . $groups . ') OR CHILD_ID IN (SELECT ID FROM mf_media_child WHERE GROUP_ID IN (' . $groups . '))');
            $connection->queryExecute('DELETE FROM mf_group_link_history WHERE GROUP_ID IN (' . $groups . ')');
            $connection->queryExecute('DELETE FROM mf_group_link WHERE GROUP_ID IN (' . $groups . ')');
        }
        if ('' !== $requestIds) {
            // MySQL refuses a subquery over the table being deleted from, hence the derived table.
            $connection->queryExecute('DELETE FROM mf_staff_request_row WHERE REQUEST_ID IN (SELECT ID FROM (' . $requestIds . ') r)');
            $connection->queryExecute('DELETE FROM mf_staff_request_history WHERE REQUEST_ID IN (SELECT ID FROM (' . $requestIds . ') r)');
            $connection->queryExecute('DELETE FROM mf_staff_request WHERE ' . implode(' OR ', $requests));
        }
    }
}
