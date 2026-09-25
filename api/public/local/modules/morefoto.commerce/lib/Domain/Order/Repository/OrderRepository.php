<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Domain\Order\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Enum\ProductionStatusEnum;
use Morefoto\Commerce\Domain\Order\Exception\OrderStorageException;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderSearchCriteria;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Заказ и строки — неизменяемый снимок покупки; поиск применяет область и фильтры до LIMIT.
 *
 * @phpstan-type OrderRecord array{
 *     PUBLIC_ID: string, GALLERY_HASH: string, QUOTE_HASH: string,
 *     INSTITUTION_ID: int, INSTITUTION_PUBLIC_ID: string, SHOOT_ID: int, SHOOT_PUBLIC_ID: string,
 *     GROUP_ID: int, GROUP_PUBLIC_ID: string, AUDIENCE: string,
 *     INSTITUTION_NAME: string, SHOOT_NAME: string, GROUP_NAME: string,
 *     BUYER_NAME: string, BUYER_PHONE: string, BUYER_EMAIL: string, BUYER_COMMENT: string, RECEIPT_CHANNEL: null|string,
 *     SUBTOTAL: int, DISCOUNT: int, GIFT_SAVING: int, TOTAL: int, ITEM_COUNT: int, GIFTS: string,
 *     CATALOG_REVISION: int, CONDITIONS_REVISION: int, PAYMENT_STATUS: string, PRODUCTION_STATUS: string, CREATED_AT: string,
 * }
 * @phpstan-type OrderLineRecord array{
 *     PUBLIC_ID: string, LINE_NO: int, ASSIGNMENT_PUBLIC_ID: string, CHILD_ID: int, CHILD_PUBLIC_ID: string, CHILD_CODE: string,
 *     PHOTO_PUBLIC_ID: null|string, PHOTO_CODE: null|string, PHOTO_WIDTH: null|int, PHOTO_HEIGHT: null|int,
 *     PRODUCT_PUBLIC_ID: string, PRODUCT_KIND: string, PRODUCT_NAME: string, PRODUCT_DESCRIPTION: string,
 *     PRODUCT_FORMAT: string, PRODUCT_UNIT: string, PRODUCT_PRICE: int, PRINT_COUNT: int, STAFF_DISCOUNT: bool,
 *     QUANTITY: int, UNIT_PRICE: int, DISCOUNT: int, TOTAL: int, COVERED_BY_GIFT: bool,
 * }
 */
final readonly class OrderRepository
{
    private const string COLUMNS = "o.ID,o.PUBLIC_ID,o.NUMBER,o.INSTITUTION_ID,o.INSTITUTION_PUBLIC_ID,o.SHOOT_ID,o.SHOOT_PUBLIC_ID,
        o.GROUP_ID,o.GROUP_PUBLIC_ID,o.AUDIENCE,o.INSTITUTION_NAME,o.SHOOT_NAME,o.GROUP_NAME,o.BUYER_NAME,o.BUYER_PHONE,o.BUYER_EMAIL,
        o.BUYER_COMMENT,o.RECEIPT_CHANNEL,o.SUBTOTAL,o.DISCOUNT,o.GIFT_SAVING,o.TOTAL,o.ITEM_COUNT,o.GIFTS,o.CATALOG_REVISION,
        o.CONDITIONS_REVISION,o.PAYMENT_STATUS,o.PRODUCTION_STATUS,o.VERSION,DATE_FORMAT(o.CREATED_AT,'%Y-%m-%d %H:%i:%s') AS CREATED_AT,
        DATE_FORMAT(o.PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,o.LATE_PAYMENT";

    /**
     * @param OrderRecord           $order
     * @param list<OrderLineRecord> $lines
     *
     * @return int внутренний ID созданного заказа
     */
    public function insert(array $order, array $lines): int
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $text = static fn(?string $value): string => null === $value ? 'NULL' : "'" . $helper->forSql($value) . "'";
        try {
            $connection->queryExecute('INSERT INTO mf_order(PUBLIC_ID,GALLERY_HASH,QUOTE_HASH,INSTITUTION_ID,INSTITUTION_PUBLIC_ID,SHOOT_ID,SHOOT_PUBLIC_ID,
                GROUP_ID,GROUP_PUBLIC_ID,AUDIENCE,INSTITUTION_NAME,SHOOT_NAME,GROUP_NAME,BUYER_NAME,BUYER_PHONE,BUYER_EMAIL,BUYER_COMMENT,
                RECEIPT_CHANNEL,SUBTOTAL,DISCOUNT,GIFT_SAVING,TOTAL,ITEM_COUNT,GIFTS,CATALOG_REVISION,CONDITIONS_REVISION,PAYMENT_STATUS,
                PRODUCTION_STATUS,VERSION,CREATED_AT,UPDATED_AT) VALUES('
                . implode(',', [
                    $text($order['PUBLIC_ID']), $text($order['GALLERY_HASH']), $text($order['QUOTE_HASH']),
                    $order['INSTITUTION_ID'], $text($order['INSTITUTION_PUBLIC_ID']), $order['SHOOT_ID'], $text($order['SHOOT_PUBLIC_ID']),
                    $order['GROUP_ID'], $text($order['GROUP_PUBLIC_ID']), $text($order['AUDIENCE']),
                    $text($order['INSTITUTION_NAME']), $text($order['SHOOT_NAME']), $text($order['GROUP_NAME']),
                    $text($order['BUYER_NAME']), $text($order['BUYER_PHONE']), $text($order['BUYER_EMAIL']), $text($order['BUYER_COMMENT']),
                    $text($order['RECEIPT_CHANNEL']), $order['SUBTOTAL'], $order['DISCOUNT'], $order['GIFT_SAVING'], $order['TOTAL'],
                    $order['ITEM_COUNT'], $text($order['GIFTS']), $order['CATALOG_REVISION'], $order['CONDITIONS_REVISION'],
                    $text($order['PAYMENT_STATUS']), $text($order['PRODUCTION_STATUS']), 1, $text($order['CREATED_AT']), $text($order['CREATED_AT']),
                ]) . ')');
            $id = (int)$connection->getInsertedId();
            $values = [];
            foreach ($lines as $line) {
                $values[] = '(' . implode(',', [
                    $text($line['PUBLIC_ID']), $id, $line['LINE_NO'], $text($line['ASSIGNMENT_PUBLIC_ID']), $line['CHILD_ID'],
                    $text($line['CHILD_PUBLIC_ID']), $text($line['CHILD_CODE']), $text($line['PHOTO_PUBLIC_ID']), $text($line['PHOTO_CODE']),
                    $line['PHOTO_WIDTH'] ?? 'NULL', $line['PHOTO_HEIGHT'] ?? 'NULL', $text($line['PRODUCT_PUBLIC_ID']), $text($line['PRODUCT_KIND']),
                    $text($line['PRODUCT_NAME']), $text($line['PRODUCT_DESCRIPTION']), $text($line['PRODUCT_FORMAT']), $text($line['PRODUCT_UNIT']),
                    $line['PRODUCT_PRICE'], $line['PRINT_COUNT'], (int)$line['STAFF_DISCOUNT'], $line['QUANTITY'], $line['UNIT_PRICE'],
                    $line['DISCOUNT'], $line['TOTAL'], (int)$line['COVERED_BY_GIFT'],
                ]) . ')';
            }
            $connection->queryExecute('INSERT INTO mf_order_line(PUBLIC_ID,ORDER_ID,LINE_NO,ASSIGNMENT_PUBLIC_ID,CHILD_ID,CHILD_PUBLIC_ID,CHILD_CODE,
                PHOTO_PUBLIC_ID,PHOTO_CODE,PHOTO_WIDTH,PHOTO_HEIGHT,PRODUCT_PUBLIC_ID,PRODUCT_KIND,PRODUCT_NAME,PRODUCT_DESCRIPTION,PRODUCT_FORMAT,
                PRODUCT_UNIT,PRODUCT_PRICE,PRINT_COUNT,STAFF_DISCOUNT,QUANTITY,UNIT_PRICE,DISCOUNT,TOTAL,COVERED_BY_GIFT) VALUES' . implode(',', $values));
        } catch (\Throwable $error) {
            if (str_contains($error->getMessage(), 'ux_mf_order_quote')) {
                throw new HttpException('QUOTE_ALREADY_USED', 409);
            }
            throw new OrderStorageException('Cannot persist order.', 0, $error);
        }

        return $id;
    }

    public function assignNumber(int $id, string $number): void
    {
        try {
            $connection = Application::getConnection();
            $connection->queryExecute("UPDATE mf_order SET NUMBER='" . $connection->getSqlHelper()->forSql($number) . "' WHERE ID={$id} AND NUMBER IS NULL");
            $affected = $connection->getAffectedRowsCount();
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot number order.', 0, $error);
        }
        if (1 !== $affected) {
            throw new OrderStorageException('Order number was assigned concurrently.');
        }
    }

    public function find(int $id): Result
    {
        return $this->query('SELECT ' . self::COLUMNS . " FROM mf_order o WHERE o.ID={$id}");
    }

    /** Блокирует строку заказа до конца транзакции вызывающего: статус оплаты меняется только под этой блокировкой. */
    public function lock(int $id): Result
    {
        return $this->query('SELECT ' . self::COLUMNS . " FROM mf_order o WHERE o.ID={$id} FOR UPDATE");
    }

    /** Меняет статус оплаты и повышает версию заказа; снимок покупки не трогает. */
    public function applyPayment(int $id, string $status, ?string $paidAt, bool $latePayment): void
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $paid = null === $paidAt ? 'NULL' : "'" . $helper->forSql($paidAt) . "'";
        try {
            $connection->queryExecute("UPDATE mf_order SET PAYMENT_STATUS='" . $helper->forSql($status) . "',PAID_AT={$paid},LATE_PAYMENT="
                . (int)$latePayment . ",VERSION=VERSION+1,UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id}");
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot change order payment status.', 0, $error);
        }
    }

    /** @param null|list<int> $institutionScope */
    public function findVisible(string $publicId, ?array $institutionScope): Result
    {
        $id = Application::getConnection()->getSqlHelper()->forSql($publicId);

        return $this->query('SELECT ' . self::COLUMNS . " FROM mf_order o WHERE o.PUBLIC_ID='{$id}'" . $this->scope($institutionScope));
    }

    /** @param non-empty-list<int> $orderIds */
    public function lines(array $orderIds): Result
    {
        return $this->query('SELECT ORDER_ID,PUBLIC_ID,LINE_NO,ASSIGNMENT_PUBLIC_ID,CHILD_ID,CHILD_PUBLIC_ID,CHILD_CODE,PHOTO_PUBLIC_ID,PHOTO_CODE,
            PHOTO_WIDTH,PHOTO_HEIGHT,PRODUCT_PUBLIC_ID,PRODUCT_KIND,PRODUCT_NAME,PRODUCT_DESCRIPTION,PRODUCT_FORMAT,PRODUCT_UNIT,PRODUCT_PRICE,
            PRINT_COUNT,STAFF_DISCOUNT,QUANTITY,UNIT_PRICE,DISCOUNT,TOTAL,COVERED_BY_GIFT
            FROM mf_order_line WHERE ORDER_ID IN (' . implode(',', $orderIds) . ') ORDER BY ORDER_ID,LINE_NO');
    }

    /**
     * Дети с хотя бы одной строкой заказа; строки заказов не удаляются, поэтому чтения без блокировки достаточно.
     *
     * @param non-empty-list<int> $childIds
     *
     * @return list<int>
     */
    public function childrenWithOrders(array $childIds): array
    {
        $result = $this->query('SELECT DISTINCT CHILD_ID FROM mf_order_line WHERE CHILD_ID IN (' . implode(',', $childIds) . ')');
        $children = [];
        while (false !== ($row = $result->fetch())) {
            $children[] = (int)$row['CHILD_ID'];
        }

        return $children;
    }

    public function page(OrderSearchCriteria $criteria, int $limit, int $offset): Result
    {
        return $this->query('SELECT ' . self::COLUMNS . ' FROM mf_order o WHERE ' . $this->where($criteria)
            . " ORDER BY o.CREATED_AT DESC,o.ID DESC LIMIT {$limit} OFFSET {$offset}");
    }

    public function count(OrderSearchCriteria $criteria): int
    {
        /** @var array{TOTAL: int|string}|false $row */
        $row = $this->query('SELECT COUNT(*) AS TOTAL FROM mf_order o WHERE ' . $this->where($criteria))->fetch();

        return false === $row ? 0 : (int)$row['TOTAL'];
    }

    /**
     * Orders of the criteria split by production status with one aggregate; every status is present, zero included.
     *
     * @return array<value-of<ProductionStatusEnum>, int>
     */
    public function productionCounts(OrderSearchCriteria $criteria): array
    {
        $counts = array_fill_keys(array_map(static fn(ProductionStatusEnum $status): string => $status->value, ProductionStatusEnum::cases()), 0);
        $result = $this->query('SELECT o.PRODUCTION_STATUS AS STATUS,COUNT(*) AS TOTAL FROM mf_order o WHERE ' . $this->where($criteria) . ' GROUP BY o.PRODUCTION_STATUS');
        while (false !== ($row = $result->fetch())) {
            if (array_key_exists((string)$row['STATUS'], $counts)) {
                $counts[(string)$row['STATUS']] = (int)$row['TOTAL'];
            }
        }

        return $counts;
    }

    private function where(OrderSearchCriteria $criteria): string
    {
        $helper = Application::getConnection()->getSqlHelper();
        $where = '1=1' . $this->scope($criteria->institutionScope);
        foreach ([
            'o.INSTITUTION_PUBLIC_ID' => $criteria->institutionId,
            'o.SHOOT_PUBLIC_ID' => $criteria->shootId,
            'o.GROUP_PUBLIC_ID' => $criteria->groupId,
            'o.PAYMENT_STATUS' => $criteria->paymentStatus,
            'o.PRODUCTION_STATUS' => $criteria->productionStatus,
        ] as $column => $value) {
            if (null !== $value) {
                $where .= " AND {$column}='" . $helper->forSql($value) . "'";
            }
        }
        if (null !== $criteria->latePayment) {
            $where .= ' AND o.LATE_PAYMENT=' . (int)$criteria->latePayment;
        }
        if (null !== $criteria->createdFrom) {
            $where .= " AND o.CREATED_AT>='" . $criteria->createdFrom->format('Y-m-d H:i:s') . "'";
        }
        if (null !== $criteria->createdBefore) {
            $where .= " AND o.CREATED_AT<'" . $criteria->createdBefore->format('Y-m-d H:i:s') . "'";
        }
        if (null !== $criteria->query) {
            $like = static fn(string $value): string => "'%" . $helper->forSql(addcslashes($value, '\%_')) . "%'";
            $conditions = ['o.BUYER_NAME LIKE ' . $like($criteria->query), 'o.BUYER_EMAIL LIKE ' . $like($criteria->query)];
            if (1 === preg_match('/^[\x20-\x7E]+$/D', $criteria->query)) {
                // NUMBER is ASCII: a Cyrillic pattern must not be coerced into it.
                $conditions[] = 'o.NUMBER LIKE ' . $like($criteria->query);
            }
            $digits = (string)preg_replace('/\D/', '', $criteria->query);
            if ('' !== $digits && 1 === preg_match('/^[+\d\s().-]+$/D', $criteria->query)) {
                $conditions[] = 'o.BUYER_PHONE LIKE ' . $like($digits);
            }
            $where .= ' AND (' . implode(' OR ', $conditions) . ')';
        }

        return $where;
    }

    /** @param null|list<int> $institutionScope */
    private function scope(?array $institutionScope): string
    {
        if (null === $institutionScope) {
            return '';
        }

        return [] === $institutionScope ? ' AND 1=0' : ' AND o.INSTITUTION_ID IN (' . implode(',', array_map('intval', $institutionScope)) . ')';
    }

    private function query(string $sql): Result
    {
        try {
            return Application::getConnection()->query($sql);
        } catch (\Throwable $error) {
            throw new OrderStorageException('Cannot read orders.', 0, $error);
        }
    }
}
