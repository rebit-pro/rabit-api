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

    /**
     * One statement keeps items and total in the same MySQL read snapshot, including an empty last page.
     * A bounded literal prefix can use ix_mf_institution_name; no claim of indexed substring search.
     */
    public function page(string $query, int $limit, int $offset): DatabaseResult
    {
        if (1 > $limit || 100 < $limit || 0 > $offset || 100 < mb_strlen($query)) {
            throw new \InvalidArgumentException('Invalid page bounds.');
        }
        try {
            $connection = Application::getConnection();
            $prefix = strtr($query, ['\\' => '\\\\', '%' => '\%', '_' => '\_']) . '%';
            $condition = '' === $query ? '1=1' : "UF_NAME LIKE '" . $connection->getSqlHelper()->forSql($prefix) . "'";

            return $connection->query(<<<SQL
SELECT page.UF_PUBLIC_ID, page.UF_NAME, page.UF_ADDRESS, page.UF_REVISION, totals.TOTAL
FROM (SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_institution WHERE {$condition}) AS totals
LEFT JOIN (
    SELECT ID, UF_PUBLIC_ID, UF_NAME, UF_ADDRESS, UF_REVISION, UF_CREATED_AT
    FROM b_hlbd_mf_institution WHERE {$condition}
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
