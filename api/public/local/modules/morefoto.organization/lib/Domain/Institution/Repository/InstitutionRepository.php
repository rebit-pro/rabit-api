<?php

declare(strict_types=1);

namespace Morefoto\Organization\Domain\Institution\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result as DatabaseResult;
use Bitrix\Main\ORM\Query\Result;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionNotFoundException;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionStorageException;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionVersionConflictException;
use Morefoto\Organization\Domain\Institution\Orm\InstitutionTable;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Domain\Calendar\Repository\GroupStateSql;

final readonly class InstitutionRepository
{
    public function find(InstitutionId $id): Result
    {
        try {
            return InstitutionTable::query()
                ->setSelect(['UF_PUBLIC_ID', 'UF_NAME', 'UF_ADDRESS', 'UF_REVISION'])
                ->where('UF_PUBLIC_ID', $id->value)->setLimit(1)->exec()
            ;
        } catch (\Throwable $exception) {
            throw new InstitutionStorageException('Cannot read institution.', 0, $exception);
        }
    }

    /** Scoped record for ORG-04; native Result is consumed inside the application boundary.
     * @param null|list<int> $institutionIds
     */
    public function visible(InstitutionId $id, ?array $institutionIds): Result
    {
        try {
            $query = InstitutionTable::query()->setSelect(['ID', 'UF_PUBLIC_ID', 'UF_NAME', 'UF_ADDRESS', 'UF_REVISION'])
                ->where('UF_PUBLIC_ID', $id->value)->setLimit(1)
            ;
            if (null !== $institutionIds) {
                foreach ($institutionIds as $institutionId) {
                    if (1 > $institutionId) {
                        throw new \InvalidArgumentException('Invalid institution scope.');
                    }
                }
                $query->whereIn('ID', [] === $institutionIds ? [0] : $institutionIds);
            }

            return $query->exec();
        } catch (\Throwable $exception) {
            throw new InstitutionStorageException('Cannot read visible institution.', 0, $exception);
        }
    }

    /**
     * @param null|list<int> $institutionIds
     *                                       One statement keeps items and total in the same MySQL read snapshot, including an empty last page.
     *                                       A bounded literal prefix can use ix_mf_institution_name; no claim of indexed substring search.
     */
    /**
     * @param null|list<int> $institutionIds visible institutions; null means all
     * @param null|string    $countsAtUtc    when set, every row also counts its shoots, groups and groups open at this UTC moment
     */
    public function page(string $query, int $limit, int $offset, ?array $institutionIds = null, ?string $countsAtUtc = null): DatabaseResult
    {
        if (1 > $limit || 100 < $limit || 0 > $offset || 100 < mb_strlen($query)) {
            throw new \InvalidArgumentException('Invalid page bounds.');
        }
        try {
            $connection = Application::getConnection();
            $prefix = strtr($query, ['\\' => '\\\\', '%' => '\%', '_' => '\_']) . '%';
            $condition = '' === $query ? '1=1' : "UF_NAME LIKE '" . $connection->getSqlHelper()->forSql($prefix) . "'";

            if (null !== $institutionIds) {
                foreach ($institutionIds as $institutionId) {
                    if (1 > $institutionId) {
                        throw new \InvalidArgumentException('Invalid scope.');
                    }
                }
                $condition .= ' AND ID IN (' . ([] === $institutionIds ? '0' : implode(',', $institutionIds)) . ')';
            }

            $counts = null === $countsAtUtc ? 'NULL AS SHOOT_COUNT, NULL AS GROUP_COUNT, NULL AS OPEN_GROUP_COUNT' : sprintf(
                '(SELECT COUNT(*) FROM b_hlbd_mf_shoot cs WHERE cs.UF_INSTITUTION_ID=i.ID) AS SHOOT_COUNT, '
                . '(SELECT COUNT(*) FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot gs ON gs.ID=g.UF_SHOOT_ID WHERE gs.UF_INSTITUTION_ID=i.ID) AS GROUP_COUNT, '
                . '(SELECT COUNT(*) FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot gs ON gs.ID=g.UF_SHOOT_ID WHERE gs.UF_INSTITUTION_ID=i.ID AND %s) AS OPEN_GROUP_COUNT',
                GroupStateSql::condition('open', $countsAtUtc),
            );

            return $connection->query(<<<SQL
SELECT page.ID, page.UF_PUBLIC_ID, page.UF_NAME, page.UF_ADDRESS, page.UF_REVISION, totals.TOTAL, page.SHOOT_COUNT, page.GROUP_COUNT, page.OPEN_GROUP_COUNT
FROM (SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_institution WHERE {$condition}) AS totals
LEFT JOIN (
    SELECT i.ID, i.UF_PUBLIC_ID, i.UF_NAME, i.UF_ADDRESS, i.UF_REVISION, i.UF_CREATED_AT, {$counts}
    FROM b_hlbd_mf_institution i WHERE {$condition}
    ORDER BY UF_CREATED_AT DESC, ID DESC LIMIT {$limit} OFFSET {$offset}
) AS page ON 1=1
ORDER BY page.UF_CREATED_AT DESC, page.ID DESC
SQL);
        } catch (\Throwable $exception) {
            throw new InstitutionStorageException('Cannot list institutions.', 0, $exception);
        }
    }

    public function create(InstitutionId $id, InstitutionDetails $details): void
    {
        try {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $name = $helper->forSql($details->name);
            $address = $helper->forSql($details->address);
            $connection->queryExecute(<<<SQL
INSERT INTO b_hlbd_mf_institution (UF_PUBLIC_ID, UF_NAME, UF_ADDRESS, UF_REVISION, UF_CREATED_AT, UF_UPDATED_AT)
VALUES ('{$id->value}', '{$name}', '{$address}', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
SQL);
        } catch (\Throwable $exception) {
            throw new InstitutionStorageException('Cannot create institution.', 0, $exception);
        }
    }

    public function update(InstitutionId $id, InstitutionDetails $details, int $expectedRevision): int
    {
        if (1 > $expectedRevision || 2147483646 < $expectedRevision) {
            throw new \InvalidArgumentException('A positive, incrementable revision is required.');
        }
        try {
            $connection = Application::getConnection();
            $helper = $connection->getSqlHelper();
            $name = $helper->forSql($details->name);
            $address = $helper->forSql($details->address);
            $connection->queryExecute(<<<SQL
UPDATE b_hlbd_mf_institution SET UF_NAME='{$name}', UF_ADDRESS='{$address}',
    UF_REVISION=UF_REVISION+1, UF_UPDATED_AT=UTC_TIMESTAMP()
WHERE UF_PUBLIC_ID='{$id->value}' AND UF_REVISION={$expectedRevision}
SQL);
            $changed = 1 === $connection->getAffectedRowsCount();
        } catch (\Throwable $exception) {
            throw new InstitutionStorageException('Cannot update institution.', 0, $exception);
        }
        if (!$changed) {
            if (false === $this->find($id)->fetch()) {
                throw new InstitutionNotFoundException('Institution does not exist.');
            }
            throw new InstitutionVersionConflictException('Institution has changed; reload before editing.');
        }

        return $expectedRevision + 1;
    }
}
