<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Sales\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;
use Morefoto\Commerce\Domain\Sales\Enum\SalesBreakdownEnum;
use Morefoto\Commerce\Domain\Sales\ValueObject\SalesArea;

/**
 * Отчёт продаж по неизменяемым снимкам заказов: одно агрегирующее чтение на разбивку, деньги — сумма строк оплаченных
 * заказов (итог заказа равен сумме его строк). «Продано» — как в E9: только `paid` без поздней оплаты, digital без
 * подарка, комплекты отдельно; заказавшими считаются дети с заказом в любом статусе оплаты.
 */
final readonly class SalesReportRepository
{
    private const string PAID = "o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=0";

    /**
     * Строка на элемент разбивки или одна итоговая строка без разбивки. Колонки: ROW_ID, NAME, ORDERS, PAID_ORDERS,
     * LATE_ORDERS, ORDERED_FAMILIES, PAID_FAMILIES, PRINTS, DIGITAL, BUNDLES, PAID_TOTAL; у детей ещё CHILD_PUBLIC_ID.
     */
    public function aggregate(SalesArea $area, ?SalesBreakdownEnum $breakdown): Result
    {
        $paid = self::PAID;
        [$key, $name] = match ($breakdown) {
            SalesBreakdownEnum::INSTITUTION => ['o.INSTITUTION_PUBLIC_ID', 'MAX(o.INSTITUTION_NAME)'],
            SalesBreakdownEnum::SHOOT => ['o.SHOOT_PUBLIC_ID', 'MAX(o.SHOOT_NAME)'],
            SalesBreakdownEnum::GROUP => ['o.GROUP_PUBLIC_ID', 'MAX(o.GROUP_NAME)'],
            SalesBreakdownEnum::CHILD => ['l.CHILD_ID', 'MAX(l.CHILD_CODE)'],
            null => ["''", "''"],
        };
        $child = SalesBreakdownEnum::CHILD === $breakdown ? 'MAX(l.CHILD_PUBLIC_ID) AS CHILD_PUBLIC_ID,' : '';
        $group = null === $breakdown ? '' : " GROUP BY {$key}";

        return $this->query("SELECT {$key} AS ROW_ID,{$name} AS NAME,{$child}COUNT(DISTINCT o.ID) AS ORDERS,
            COUNT(DISTINCT CASE WHEN {$paid} THEN o.ID END) AS PAID_ORDERS,
            COUNT(DISTINCT CASE WHEN o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=1 THEN o.ID END) AS LATE_ORDERS,
            COUNT(DISTINCT l.CHILD_ID) AS ORDERED_FAMILIES,
            COUNT(DISTINCT CASE WHEN {$paid} THEN l.CHILD_ID END) AS PAID_FAMILIES,
            COALESCE(SUM(CASE WHEN {$paid} AND l.PRODUCT_KIND='physical' THEN l.QUANTITY*l.PRINT_COUNT ELSE 0 END),0) AS PRINTS,
            COALESCE(SUM(CASE WHEN {$paid} AND l.PRODUCT_KIND='digital' AND l.COVERED_BY_GIFT=0 AND l.PHOTO_PUBLIC_ID IS NOT NULL THEN l.QUANTITY ELSE 0 END),0) AS DIGITAL,
            COALESCE(SUM(CASE WHEN {$paid} AND l.PRODUCT_KIND='bundle' AND l.COVERED_BY_GIFT=0 THEN l.QUANTITY ELSE 0 END),0) AS BUNDLES,
            COALESCE(SUM(CASE WHEN {$paid} THEN l.TOTAL ELSE 0 END),0) AS PAID_TOTAL
            FROM mf_order o INNER JOIN mf_order_line l ON l.ORDER_ID=o.ID
            WHERE {$this->where($area)}{$group}");
    }

    /** Отпечатки оплаченных заказов области по продукции; название — из снимка, одна строка на продукцию. */
    public function products(SalesArea $area): Result
    {
        $paid = self::PAID;

        return $this->query("SELECT l.PRODUCT_PUBLIC_ID,MAX(l.PRODUCT_NAME) AS NAME,SUM(l.QUANTITY*l.PRINT_COUNT) AS PRINTS
            FROM mf_order o INNER JOIN mf_order_line l ON l.ORDER_ID=o.ID
            WHERE {$this->where($area)} AND {$paid} AND l.PRODUCT_KIND='physical'
            GROUP BY l.PRODUCT_PUBLIC_ID ORDER BY PRINTS DESC,NAME,l.PRODUCT_PUBLIC_ID");
    }

    /** Одна строка на заказ в разрешённой области; ФИО принадлежит заказу, а не детям его строк. */
    public function orders(SalesArea $area): Result
    {
        return $this->query("SELECT o.PUBLIC_ID,o.NUMBER,o.CHILD_FIRST_NAME,o.CHILD_LAST_NAME,o.PAYMENT_STATUS,o.LATE_PAYMENT,o.TOTAL
            FROM mf_order o WHERE {$this->where($area)} ORDER BY o.CREATED_AT DESC,o.ID DESC");
    }

    private function where(SalesArea $area): string
    {
        $conditions = ['1=1'];
        if (null !== $area->institutionIds) {
            $conditions[] = $this->in('o.INSTITUTION_ID', $area->institutionIds);
        }
        if (null !== $area->groupIds) {
            $conditions[] = $this->in('o.GROUP_ID', $area->groupIds);
        }
        foreach (['o.INSTITUTION_PUBLIC_ID' => $area->institutionId, 'o.SHOOT_PUBLIC_ID' => $area->shootId, 'o.GROUP_PUBLIC_ID' => $area->groupId] as $column => $id) {
            if (null !== $id) {
                $conditions[] = "{$column}='{$this->uuid($id)}'";
            }
        }

        return implode(' AND ', $conditions);
    }

    /** @param list<int> $ids */
    private function in(string $column, array $ids): string
    {
        return [] === $ids ? '1=0' : $column . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
    }

    private function uuid(string $value): string
    {
        if (1 !== preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $value)) {
            throw new \InvalidArgumentException('Invalid sales area ID.');
        }

        return $value;
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot read sales report.', 0, $error);
        }
    }
}
