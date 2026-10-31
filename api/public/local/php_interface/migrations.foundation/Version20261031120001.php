<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261031120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Store the child first and last name once per order; historical orders keep null values';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_order')) {
            throw new \RuntimeException('Child names require the merged orders schema.');
        }
        if (false !== $connection->query("SHOW COLUMNS FROM mf_order LIKE 'CHILD_FIRST_NAME'")->fetch()) {
            return;
        }
        $connection->queryExecute('ALTER TABLE mf_order'
            . ' ADD COLUMN CHILD_FIRST_NAME VARCHAR(100) NULL AFTER BUYER_PHONE,'
            . ' ADD COLUMN CHILD_LAST_NAME VARCHAR(100) NULL AFTER CHILD_FIRST_NAME,'
            . ' ADD CONSTRAINT ck_mf_order_child_name CHECK ('
            . ' (CHILD_FIRST_NAME IS NULL AND CHILD_LAST_NAME IS NULL)'
            . ' OR (CHILD_FIRST_NAME IS NOT NULL AND CHILD_LAST_NAME IS NOT NULL'
            . ' AND CHAR_LENGTH(TRIM(CHILD_FIRST_NAME)) BETWEEN 1 AND 100'
            . ' AND CHAR_LENGTH(TRIM(CHILD_LAST_NAME)) BETWEEN 1 AND 100))');
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (false === $connection->query("SHOW COLUMNS FROM mf_order LIKE 'CHILD_FIRST_NAME'")->fetch()) {
            return;
        }
        if (false !== $connection->query('SELECT 1 FROM mf_order WHERE CHILD_FIRST_NAME IS NOT NULL OR CHILD_LAST_NAME IS NOT NULL LIMIT 1')->fetch()) {
            throw new \RuntimeException('Orders contain child names; dropping the columns would destroy them.');
        }
        $connection->queryExecute('ALTER TABLE mf_order DROP CHECK ck_mf_order_child_name, DROP COLUMN CHILD_LAST_NAME, DROP COLUMN CHILD_FIRST_NAME');
    }
}
