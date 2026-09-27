<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Organization;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Commerce\StructureSalesRemovalInterface;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Orders keep the structure for their history and payments, so any order refuses the removal.
 * Carts and unfinished checkouts hold the gallery key that Media deletes next, so they go first.
 */
final readonly class StructureSalesRemoval implements StructureSalesRemovalInterface
{
    public function remove(StructureRemovalDto $removal): void
    {
        // Native IDs read by Organization under its locks: integers only, safe to inline.
        $groups = implode(',', $removal->groupIds);
        $conditions = [];
        foreach (['GROUP_ID' => $groups, 'SHOOT_ID' => implode(',', $removal->shootIds), 'INSTITUTION_ID' => implode(',', $removal->institutionIds)] as $column => $ids) {
            if ('' !== $ids) {
                $conditions[] = $column . ' IN (' . $ids . ')';
            }
        }
        if ([] === $conditions) {
            return;
        }
        $connection = Application::getConnection();
        if (false !== $connection->query('SELECT 1 FROM mf_order WHERE ' . implode(' OR ', $conditions) . ' LIMIT 1')->fetch()) {
            throw new HttpException('STRUCTURE_HAS_ORDERS', 409);
        }
        if ('' === $groups) {
            return;
        }
        $galleries = 'SELECT c.TOKEN_HASH FROM mf_gallery_capability c INNER JOIN b_hlbd_mf_group g ON g.UF_PUBLIC_ID=c.GROUP_PUBLIC_ID WHERE g.ID IN (' . $groups . ')';
        $connection->queryExecute('DELETE FROM mf_order_checkout WHERE ORDER_ID IS NULL AND GALLERY_HASH IN (' . $galleries . ')');
        $connection->queryExecute('DELETE FROM mf_cart_quote WHERE GALLERY_HASH IN (' . $galleries . ')');
        $connection->queryExecute('DELETE FROM mf_group_product_condition WHERE GROUP_ID IN (' . $groups . ')');
        $connection->queryExecute('DELETE FROM mf_group_sales_conditions WHERE GROUP_ID IN (' . $groups . ')');
    }
}
