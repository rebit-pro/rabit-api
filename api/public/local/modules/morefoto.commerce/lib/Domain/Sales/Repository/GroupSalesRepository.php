<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;

/**
 * Продажи группы по неизменяемому снимку заказов: только подтверждённая оплата без поздней (E9-DEC-05), чтение по индексу
 * `ix_mf_order_group`. Цифровые файлы — кадры digital не по подарку (E9-DEC-01), комплекты bundle — отдельно (E9-DEC-04).
 */
final readonly class GroupSalesRepository
{
    /**
     * @return array{ORDERS: int, FAMILIES: int, PRINTS: int, DIGITAL: int, BUNDLES: int, PAID_TOTAL: int}
     */
    public function totals(string $groupPublicId): array
    {
        $group = $this->groupId($groupPublicId);
        $paid = $this->paid('o');
        $paidOrder = $this->paid('p');
        // An order always has lines; the paid sum is read from orders alone so the join does not repeat it per line.
        $row = $this->query("SELECT COUNT(DISTINCT o.ID) AS ORDERS,COUNT(DISTINCT l.CHILD_ID) AS FAMILIES,
            COALESCE(SUM(CASE WHEN l.PRODUCT_KIND='physical' THEN l.QUANTITY*l.PRINT_COUNT ELSE 0 END),0) AS PRINTS,
            COALESCE(SUM(CASE WHEN l.PRODUCT_KIND='digital' AND l.COVERED_BY_GIFT=0 AND l.PHOTO_PUBLIC_ID IS NOT NULL THEN l.QUANTITY ELSE 0 END),0) AS DIGITAL,
            COALESCE(SUM(CASE WHEN l.PRODUCT_KIND='bundle' AND l.COVERED_BY_GIFT=0 THEN l.QUANTITY ELSE 0 END),0) AS BUNDLES,
            (SELECT COALESCE(SUM(p.TOTAL),0) FROM mf_order p WHERE p.GROUP_PUBLIC_ID='{$group}' AND {$paidOrder}) AS PAID_TOTAL
            FROM mf_order o INNER JOIN mf_order_line l ON l.ORDER_ID=o.ID
            WHERE o.GROUP_PUBLIC_ID='{$group}' AND {$paid}")->fetch() ?: [];

        return [
            'ORDERS' => (int)($row['ORDERS'] ?? 0),
            'FAMILIES' => (int)($row['FAMILIES'] ?? 0),
            'PRINTS' => (int)($row['PRINTS'] ?? 0),
            'DIGITAL' => (int)($row['DIGITAL'] ?? 0),
            'BUNDLES' => (int)($row['BUNDLES'] ?? 0),
            'PAID_TOTAL' => (int)($row['PAID_TOTAL'] ?? 0),
        ];
    }

    /** Отпечатки по продукции; название — из снимка, при переименовании между заказами одна строка на продукцию. */
    public function printsByProduct(string $groupPublicId): Result
    {
        $group = $this->groupId($groupPublicId);
        $paid = $this->paid('o');

        return $this->query("SELECT l.PRODUCT_PUBLIC_ID,MAX(l.PRODUCT_NAME) AS NAME,SUM(l.QUANTITY*l.PRINT_COUNT) AS PRINTS
            FROM mf_order o INNER JOIN mf_order_line l ON l.ORDER_ID=o.ID
            WHERE o.GROUP_PUBLIC_ID='{$group}' AND {$paid} AND l.PRODUCT_KIND='physical'
            GROUP BY l.PRODUCT_PUBLIC_ID ORDER BY PRINTS DESC,NAME,l.PRODUCT_PUBLIC_ID");
    }

    private function paid(string $alias): string
    {
        return "{$alias}.PAYMENT_STATUS='paid' AND {$alias}.LATE_PAYMENT=0";
    }

    private function groupId(string $groupPublicId): string
    {
        if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $groupPublicId)) {
            throw new \InvalidArgumentException('Invalid group ID.');
        }

        return $groupPublicId;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot read group sales.', 0, $error);
        }
    }
}
