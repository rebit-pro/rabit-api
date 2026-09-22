<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Conditions\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Conditions\Dto\ProductConditionInputDto;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;

final readonly class SalesConditionsRepository
{
    private const string PRODUCT_FIELDS = 'p.UF_UUID,p.UF_NAME,p.UF_DESCRIPTION,p.UF_KIND,p.UF_PRICE,p.UF_PRINT_COUNT,p.UF_FORMAT,p.UF_UNIT,p.UF_STAFF_DISCOUNT,p.UF_ACTIVE';

    /** @return array{REVISION: int|string, GIFT_THRESHOLD: int|string, GIFT_FOR_STAFF: int|string} */
    public function global(bool $forUpdate): array
    {
        $row = $this->query('SELECT REVISION,GIFT_THRESHOLD,GIFT_FOR_STAFF FROM mf_sales_conditions WHERE ID=1 ' . ($forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE'))->fetch();
        if (false === $row) {
            throw new ConditionsStorageException('Global sales conditions are not installed.');
        }

        return $row;
    }

    /** @return array{REVISION: int|string, INHERIT: int|string, GIFT_THRESHOLD: int|string, GIFT_FOR_STAFF: int|string}|false */
    public function group(int $groupId, bool $forUpdate): array|false
    {
        return $this->query('SELECT REVISION,INHERIT,GIFT_THRESHOLD,GIFT_FOR_STAFF FROM mf_group_sales_conditions WHERE GROUP_ID=' . $this->positive($groupId) . ' ' . ($forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE'))->fetch();
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, array{REVISION: int|string, INHERIT: int|string, GIFT_THRESHOLD: int|string, GIFT_FOR_STAFF: int|string}>
     */
    public function groups(array $groupIds): array
    {
        $ids = implode(',', array_map($this->positive(...), $groupIds));
        $result = $this->query('SELECT GROUP_ID,REVISION,INHERIT,GIFT_THRESHOLD,GIFT_FOR_STAFF FROM mf_group_sales_conditions WHERE GROUP_ID IN (' . $ids . ') LOCK IN SHARE MODE');
        $groups = [];
        while (false !== ($row = $result->fetch())) {
            $groups[(int)$row['GROUP_ID']] = $row;
        }

        return $groups;
    }

    public function globalProducts(): Result
    {
        return $this->query('SELECT ' . self::PRODUCT_FIELDS . ' FROM b_hlbd_mf_product p ORDER BY p.UF_NAME ASC,p.ID ASC');
    }

    public function groupProducts(int $groupId): Result
    {
        return $this->query('SELECT p.UF_UUID,p.UF_NAME,p.UF_DESCRIPTION,p.UF_KIND,'
            . 'COALESCE(c.PRICE,p.UF_PRICE) AS UF_PRICE,p.UF_PRINT_COUNT,p.UF_FORMAT,p.UF_UNIT,'
            . 'COALESCE(c.STAFF_DISCOUNT,p.UF_STAFF_DISCOUNT) AS UF_STAFF_DISCOUNT,'
            . 'CASE WHEN c.PRODUCT_UUID IS NULL THEN 0 ELSE p.UF_ACTIVE*c.ACTIVE END AS UF_ACTIVE '
            . 'FROM b_hlbd_mf_product p LEFT JOIN mf_group_product_condition c ON c.PRODUCT_UUID=p.UF_UUID AND c.GROUP_ID=' . $this->positive($groupId)
            . ' ORDER BY p.UF_NAME ASC,p.ID ASC');
    }

    public function hasIncompatibleGroupOverrides(): bool
    {
        return false !== $this->query(
            'SELECT g.GROUP_ID FROM mf_group_sales_conditions g '
            . 'LEFT JOIN mf_group_product_condition c ON c.GROUP_ID=g.GROUP_ID '
            . 'LEFT JOIN b_hlbd_mf_product p ON p.UF_UUID=c.PRODUCT_UUID '
            . 'WHERE g.INHERIT=0 GROUP BY g.GROUP_ID,g.GIFT_THRESHOLD '
            . 'HAVING SUM(CASE WHEN c.ACTIVE=1 AND (p.UF_UUID IS NULL OR p.UF_ACTIVE=0) THEN 1 ELSE 0 END)>0 '
            . "OR (g.GIFT_THRESHOLD>0 AND SUM(CASE WHEN c.ACTIVE=1 AND p.UF_ACTIVE=1 AND p.UF_KIND='bundle' THEN 1 ELSE 0 END)<>1) LIMIT 1",
        )->fetch();
    }

    public function updateGlobalProduct(ProductConditionInputDto $product): void
    {
        $this->execute('UPDATE b_hlbd_mf_product SET UF_PRICE=' . $product->price . ',UF_ACTIVE=' . (int)$product->active
            . ',UF_STAFF_DISCOUNT=' . (int)$product->staffDiscount . ",UF_UPDATED_AT=UTC_TIMESTAMP() WHERE UF_UUID='" . $product->id->value . "'");
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            $exists = $this->query("SELECT 1 FROM b_hlbd_mf_product WHERE UF_UUID='" . $product->id->value . "'")->fetch();
            if (false === $exists) {
                throw new ConditionsStorageException('Catalogue product disappeared during the condition update.');
            }
        }
    }

    public function advanceGlobal(int $current, int $threshold, bool $giftForStaff): int
    {
        $next = $this->next($current);
        $this->execute('UPDATE mf_sales_conditions SET REVISION=' . $next . ',GIFT_THRESHOLD=' . $threshold . ',GIFT_FOR_STAFF=' . (int)$giftForStaff . ',UPDATED_AT=UTC_TIMESTAMP() WHERE ID=1 AND REVISION=' . $current);
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new ConditionsStorageException('Cannot advance global sales condition revision.');
        }

        return $next;
    }

    /** @param list<ProductConditionInputDto> $products */
    public function replaceGroupProducts(int $groupId, array $products): void
    {
        $groupId = $this->positive($groupId);
        $this->execute('DELETE FROM mf_group_product_condition WHERE GROUP_ID=' . $groupId);
        foreach ($products as $product) {
            $this->execute('INSERT INTO mf_group_product_condition(GROUP_ID,PRODUCT_UUID,PRICE,ACTIVE,STAFF_DISCOUNT) VALUES('
                . $groupId . ",'" . $product->id->value . "'," . $product->price . ',' . (int)$product->active . ',' . (int)$product->staffDiscount . ')');
        }
    }

    public function saveGroup(int $groupId, int $current, bool $inherit, int $threshold, bool $giftForStaff): int
    {
        $groupId = $this->positive($groupId);
        $next = $this->next($current);
        if (0 === $current) {
            $this->execute('INSERT INTO mf_group_sales_conditions(GROUP_ID,REVISION,INHERIT,GIFT_THRESHOLD,GIFT_FOR_STAFF,UPDATED_AT) VALUES('
                . $groupId . ',' . $next . ',' . (int)$inherit . ',' . $threshold . ',' . (int)$giftForStaff . ',UTC_TIMESTAMP())');

            return $next;
        }
        $this->execute('UPDATE mf_group_sales_conditions SET REVISION=' . $next . ',INHERIT=' . (int)$inherit . ',GIFT_THRESHOLD=' . $threshold
            . ',GIFT_FOR_STAFF=' . (int)$giftForStaff . ',UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=' . $groupId . ' AND REVISION=' . $current);
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new ConditionsStorageException('Cannot advance group sales condition revision.');
        }

        return $next;
    }

    private function next(int $current): int
    {
        if (PHP_INT_MAX === $current) {
            throw new ConditionsStorageException('Sales condition revision exhausted.');
        }

        return $current + 1;
    }

    private function positive(int $value): int
    {
        if (1 > $value) {
            throw new \InvalidArgumentException('Positive native group ID required.');
        }

        return $value;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $exception) {
            throw new ConditionsStorageException('Cannot read sales conditions.', 0, $exception);
        }
    }

    private function execute(string $sql): void
    {
        try {
            Application::getConnection()->queryExecute($sql);
        } catch (\Throwable $exception) {
            throw new ConditionsStorageException('Cannot persist sales conditions.', 0, $exception);
        }
    }
}
