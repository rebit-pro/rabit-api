<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Structure\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureName;
use Morefoto\Organization\Domain\Structure\ValueObject\ShootDate;
use Morefoto\Organization\Domain\Structure\Exception\StructureStorageException;
use Morefoto\Organization\Domain\Structure\Exception\StructureVersionConflictException;

/**
 * SQL date expressions intentionally return strings: native Bitrix converts bare DATE/DATETIME columns to mutable platform objects.
 *
 * @phpstan-type InstitutionRow array{
 *     ID: int|string,
 *     UF_PUBLIC_ID: string,
 * }
 * @phpstan-type ShootRow array{
 *     ID: int|string,
 *     UF_PUBLIC_ID: string,
 *     UF_INSTITUTION_ID: int|string,
 *     UF_NAME: string,
 *     UF_DATE: string|null,
 *     UF_REVISION: int|string,
 *     INSTITUTION_PUBLIC_ID?: string,
 * }
 * @phpstan-type GroupRow array{
 *     ID: int|string,
 *     UF_PUBLIC_ID: string,
 *     UF_SHOOT_ID: int|string,
 *     UF_NAME: string,
 *     UF_KIND: string,
 *     UF_REVISION: int|string,
 *     UF_INSTITUTION_ID?: int|string,
 *     SHOOT_PUBLIC_ID?: string,
 * }
 */
final readonly class StructureRepository
{
    /** One InstitutionRow per result; scope is applied inside SQL. @param null|list<int> $institutionIds */
    public function institution(StructureId $id, ?array $institutionIds = null): Result
    {
        return $this->query("SELECT ID,UF_PUBLIC_ID FROM b_hlbd_mf_institution WHERE UF_PUBLIC_ID='{$id->value}'" . $this->scope($institutionIds, 'ID'));
    }

    /** One ShootRow per result. */
    public function shoot(StructureId $id): Result
    {
        return $this->query("SELECT s.ID,s.UF_PUBLIC_ID,s.UF_INSTITUTION_ID,s.UF_NAME,DATE_FORMAT(s.UF_DATE,'%Y-%m-%d') AS UF_DATE,s.UF_REVISION,i.UF_PUBLIC_ID AS INSTITUTION_PUBLIC_ID FROM b_hlbd_mf_shoot s INNER JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID WHERE s.UF_PUBLIC_ID='{$id->value}'");
    }

    /** One GroupRow per result. */
    public function group(StructureId $id): Result
    {
        return $this->query("SELECT g.ID,g.UF_PUBLIC_ID,g.UF_SHOOT_ID,g.UF_NAME,g.UF_KIND,g.UF_REVISION,s.UF_INSTITUTION_ID,s.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE g.UF_PUBLIC_ID='{$id->value}'");
    }

    public function lockInstitution(int $id): Result
    {
        return $this->query("SELECT ID,UF_PUBLIC_ID FROM b_hlbd_mf_institution WHERE ID={$id} FOR UPDATE");
    }

    public function lockShoot(int $id): Result
    {
        return $this->query("SELECT ID,UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,DATE_FORMAT(UF_DATE,'%Y-%m-%d') AS UF_DATE,UF_REVISION FROM b_hlbd_mf_shoot WHERE ID={$id} FOR UPDATE");
    }

    public function lockGroup(int $id): Result
    {
        return $this->query("SELECT ID,UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$id} FOR UPDATE");
    }

    /** @param null|list<int> $institutionIds */
    public function shoots(int $institutionId, int $limit, int $offset, ?array $institutionIds): Result
    {
        $this->bounds($limit, $offset);
        $condition = 'UF_INSTITUTION_ID=' . $institutionId . $this->scope($institutionIds, 'UF_INSTITUTION_ID');

        return $this->query(<<<SQL
SELECT page.ID,page.UF_PUBLIC_ID,page.UF_NAME,DATE_FORMAT(page.UF_DATE,'%Y-%m-%d') AS UF_DATE,page.UF_REVISION,totals.TOTAL
FROM (SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_shoot WHERE {$condition}) totals
LEFT JOIN (
    SELECT ID,UF_PUBLIC_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT FROM b_hlbd_mf_shoot
    WHERE {$condition} ORDER BY UF_CREATED_AT DESC,ID DESC LIMIT {$limit} OFFSET {$offset}
) page ON 1=1 ORDER BY page.UF_CREATED_AT DESC,page.ID DESC
SQL);
    }

    public function groups(int $shootId, int $limit, int $offset): Result
    {
        $this->bounds($limit, $offset);

        return $this->query(<<<SQL
SELECT page.ID,page.UF_PUBLIC_ID,page.UF_NAME,page.UF_KIND,page.UF_REVISION,page.UF_TIMEZONE,
DATE_FORMAT(page.UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS UF_SENT_AT,DATE_FORMAT(page.UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS UF_CLOSES_AT,DATE_FORMAT(page.UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS UF_DELIVERY_DUE_AT,totals.TOTAL
FROM (SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_group WHERE UF_SHOOT_ID={$shootId}) totals
LEFT JOIN (
    SELECT ID,UF_PUBLIC_ID,UF_NAME,UF_KIND,UF_REVISION,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DELIVERY_DUE_AT,UF_CREATED_AT
    FROM b_hlbd_mf_group WHERE UF_SHOOT_ID={$shootId}
    ORDER BY UF_CREATED_AT DESC,ID DESC LIMIT {$limit} OFFSET {$offset}
) page ON 1=1 ORDER BY page.UF_CREATED_AT DESC,page.ID DESC
SQL);
    }

    /** Groups across every shoot of one institution, independently paged from shoots.
     * @param null|list<int> $institutionIds
     *
     * Each Result row has this shape (nullable page fields represent an empty page with a real total):
     * array{
     *     ID: int|string|null, UF_PUBLIC_ID: string|null, SHOOT_PUBLIC_ID: string|null,
     *     UF_NAME: string|null, UF_KIND: string|null, UF_REVISION: int|string|null,
     *     UF_TIMEZONE: string|null, UF_SENT_AT: string|null, UF_CLOSES_AT: string|null,
     *     UF_DELIVERY_DUE_AT: string|null, TOTAL: int|string,
     * }
     */
    public function institutionGroups(int $institutionId, int $limit, int $offset, ?array $institutionIds): Result
    {
        $this->bounds($limit, $offset);
        $condition = 's.UF_INSTITUTION_ID=' . $institutionId . $this->scope($institutionIds, 's.UF_INSTITUTION_ID');

        return $this->query(<<<SQL
SELECT page.ID,page.UF_PUBLIC_ID,page.SHOOT_PUBLIC_ID,page.UF_NAME,page.UF_KIND,page.UF_REVISION,page.UF_TIMEZONE,
DATE_FORMAT(page.UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS UF_SENT_AT,DATE_FORMAT(page.UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS UF_CLOSES_AT,DATE_FORMAT(page.UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS UF_DELIVERY_DUE_AT,totals.TOTAL
FROM (SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE {$condition}) totals
LEFT JOIN (
    SELECT g.ID,g.UF_PUBLIC_ID,s.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID,g.UF_NAME,g.UF_KIND,g.UF_REVISION,g.UF_TIMEZONE,g.UF_SENT_AT,g.UF_CLOSES_AT,g.UF_DELIVERY_DUE_AT,g.UF_CREATED_AT
    FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE {$condition}
    ORDER BY g.UF_CREATED_AT DESC,g.ID DESC LIMIT {$limit} OFFSET {$offset}
) page ON 1=1 ORDER BY page.UF_CREATED_AT DESC,page.ID DESC
SQL);
    }

    public function createShoot(StructureId $id, int $institutionId, StructureName $name, ShootDate $date): int
    {
        $nameSql = $this->quote($name->value);
        $dateSql = null === $date->value ? 'NULL' : $this->quote($date->value);
        $this->execute("INSERT INTO b_hlbd_mf_shoot(UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$id->value}',{$institutionId},{$nameSql},{$dateSql},1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");

        return (int)Application::getConnection()->getInsertedId();
    }

    public function updateShoot(int $id, StructureName $name, ShootDate $date, int $revision): int
    {
        $nameSql = $this->quote($name->value);
        $dateSql = null === $date->value ? 'NULL' : $this->quote($date->value);
        $this->execute("UPDATE b_hlbd_mf_shoot SET UF_NAME={$nameSql},UF_DATE={$dateSql},UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND UF_REVISION={$revision}");

        return $this->changed($revision);
    }

    public function createGroup(StructureId $id, int $shootId, StructureName $name, string $kind): int
    {
        if (!in_array($kind, ['regular', 'staff'], true)) {
            throw new \InvalidArgumentException('Invalid group kind.');
        }
        $nameSql = $this->quote($name->value);
        $this->execute("INSERT INTO b_hlbd_mf_group(UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DELIVERY_DUE_AT,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$id->value}',{$shootId},{$nameSql},'{$kind}','Europe/Moscow',NULL,NULL,NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");

        return (int)Application::getConnection()->getInsertedId();
    }

    public function updateGroup(int $id, StructureName $name, int $revision): int
    {
        $nameSql = $this->quote($name->value);
        $this->execute("UPDATE b_hlbd_mf_group SET UF_NAME={$nameSql},UF_REVISION=UF_REVISION+1,UF_UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND UF_REVISION={$revision}");

        return $this->changed($revision);
    }

    private function changed(int $revision): int
    {
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new StructureVersionConflictException('The structure changed; reload before editing.');
        }

        return $revision + 1;
    }

    /** @param null|list<int> $ids */
    private function scope(?array $ids, string $column): string
    {
        if (null === $ids) {
            return '';
        }
        foreach ($ids as $id) {
            if (1 > $id) {
                throw new \InvalidArgumentException('Invalid institution scope.');
            }
        }

        return ' AND ' . $column . ' IN (' . ([] === $ids ? '0' : implode(',', $ids)) . ')';
    }

    private function bounds(int $limit, int $offset): void
    {
        if (1 > $limit || 100 < $limit || 0 > $offset || 100000000 < $offset) {
            throw new \InvalidArgumentException('Invalid page bounds.');
        }
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
            throw new StructureStorageException('Cannot read organization structure.', 0, $error);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $error) {
            throw new StructureStorageException('Cannot persist organization structure.', 0, $error);
        }
    }
}
