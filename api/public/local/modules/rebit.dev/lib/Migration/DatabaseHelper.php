<?php

declare(strict_types=1);

namespace Rebit\Dev\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;

final class DatabaseHelper
{
    /**
     * @throws SqlQueryException
     */
    public static function hasIndexByName(string $table, string $indexName): bool
    {
        $connection = Application::getConnection();
        $db = self::getDbName();

        $tableEsc = $connection->getSqlHelper()->forSql($table);
        $indexEsc = $connection->getSqlHelper()->forSql($indexName);
        $dbEsc = $connection->getSqlHelper()->forSql($db);

        $res = $connection->query("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = '{$dbEsc}'
              AND TABLE_NAME = '{$tableEsc}'
              AND INDEX_NAME = '{$indexEsc}'
            LIMIT 1
        ");

        return (bool)$res->fetch();
    }

    /**
     * @throws SqlQueryException
     */
    public static function hasIndexByColumns(string $table, array $columns): bool
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $db = self::getDbName();

        $tableEsc = $helper->forSql($table);
        $dbEsc = $helper->forSql($db);

        $res = $connection->query("
            SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = '{$dbEsc}'
              AND TABLE_NAME = '{$tableEsc}'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ");

        $byIndex = [];
        while ($row = $res->fetch()) {
            // игнорируем PRIMARY и уникальные индексы
            if (0 === (int)$row['NON_UNIQUE'] && 'PRIMARY' !== $row['INDEX_NAME']) {
                continue;
            }
            $byIndex[$row['INDEX_NAME']][$row['SEQ_IN_INDEX']] = $row['COLUMN_NAME'];
        }

        // нормализуем порядок колонок
        foreach ($byIndex as &$cols) {
            ksort($cols, SORT_NUMERIC);
            $cols = array_values($cols);
        }
        unset($cols);

        $target = array_values($columns);

        return [] !== array_filter($byIndex, fn($cols) => $cols === $target);
    }

    /**
     * @throws SqlQueryException
     */
    public static function getDbName(): string
    {
        $connection = Application::getConnection();

        $res = $connection->query('SELECT DATABASE() as DB')->fetch();

        return (string)($res['DB'] ?? '');
    }
}
