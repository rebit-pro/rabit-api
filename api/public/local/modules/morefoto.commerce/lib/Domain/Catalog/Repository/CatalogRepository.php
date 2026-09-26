<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Catalog\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductDetails;
use Morefoto\Commerce\Domain\Catalog\ValueObject\ProductId;

final readonly class CatalogRepository
{
    private const string FIELDS = 'UF_UUID, UF_NAME, UF_DESCRIPTION, UF_KIND, UF_PRICE, UF_PRINT_COUNT, UF_FORMAT, UF_UNIT, UF_STAFF_DISCOUNT, UF_ACTIVE';

    /** Must be the first read inside the catalogue transaction. */
    public function lockRevision(bool $forUpdate): int
    {
        $locking = $forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE';
        $row = Application::getConnection()->query('SELECT REVISION FROM mf_catalog_state WHERE ID = 1 ' . $locking)->fetch();
        if (false === $row || 1 > (int)$row['REVISION']) {
            throw new CatalogStorageException('Catalog state is not installed.');
        }

        return (int)$row['REVISION'];
    }

    public function list(int $offset, int $limit, bool $byName = false): Result
    {
        return Application::getConnection()->query('SELECT ' . self::FIELDS . ' FROM b_hlbd_mf_product ORDER BY ' . ($byName ? 'UF_NAME ASC, ID ASC' : 'ID ASC') . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
    }

    public function find(ProductId $id): Result
    {
        return Application::getConnection()->query('SELECT ' . self::FIELDS . " FROM b_hlbd_mf_product WHERE UF_UUID = '" . $id->value . "'");
    }

    public function count(): int
    {
        $row = Application::getConnection()->query('SELECT COUNT(*) AS TOTAL FROM b_hlbd_mf_product')->fetch();

        return (int)$row['TOTAL'];
    }

    public function add(ProductId $id, ProductDetails $details): void
    {
        Application::getConnection()->queryExecute("INSERT INTO b_hlbd_mf_product SET UF_UUID = '" . $id->value . "', " . $this->fields($details) . ', UF_CREATED_AT = UTC_TIMESTAMP(), UF_UPDATED_AT = UTC_TIMESTAMP()');
    }

    public function update(ProductId $id, ProductDetails $details): void
    {
        Application::getConnection()->queryExecute('UPDATE b_hlbd_mf_product SET ' . $this->fields($details) . ", UF_UPDATED_AT = UTC_TIMESTAMP() WHERE UF_UUID = '" . $id->value . "'");
    }

    public function purchased(ProductId $id): bool
    {
        return false !== Application::getConnection()->query("SELECT 1 FROM mf_order_line WHERE PRODUCT_PUBLIC_ID = '" . $id->value . "' LIMIT 1")->fetch();
    }

    /** Group conditions that offered the product lose it; their revision moves so open editors reload. */
    public function delete(ProductId $id): void
    {
        $connection = Application::getConnection();
        $connection->queryExecute("UPDATE mf_group_sales_conditions c INNER JOIN mf_group_product_condition p ON p.GROUP_ID = c.GROUP_ID SET c.REVISION = c.REVISION + 1, c.UPDATED_AT = UTC_TIMESTAMP() WHERE p.PRODUCT_UUID = '" . $id->value . "'");
        $connection->queryExecute("DELETE FROM mf_group_product_condition WHERE PRODUCT_UUID = '" . $id->value . "'");
        $connection->queryExecute("DELETE FROM b_hlbd_mf_product WHERE UF_UUID = '" . $id->value . "'");
    }

    public function advanceRevision(int $current): int
    {
        if (PHP_INT_MAX === $current) {
            throw new CatalogStorageException('Catalog revision exhausted.');
        }
        $next = $current + 1;
        Application::getConnection()->queryExecute('UPDATE mf_catalog_state SET REVISION = ' . $next . ' WHERE ID = 1');

        return $next;
    }

    private function fields(ProductDetails $details): string
    {
        $helper = Application::getConnection()->getSqlHelper();

        return "UF_NAME = '" . $helper->forSql($details->name) . "', UF_DESCRIPTION = '" . $helper->forSql($details->description)
            . "', UF_KIND = '" . $details->kind->value . "', UF_PRICE = " . $details->price . ', UF_PRINT_COUNT = ' . $details->printCount
            . ", UF_FORMAT = '" . $helper->forSql($details->format) . "', UF_UNIT = '" . $helper->forSql($details->unit)
            . "', UF_STAFF_DISCOUNT = " . (int)$details->staffDiscount . ', UF_ACTIVE = ' . (int)$details->active;
    }
}
