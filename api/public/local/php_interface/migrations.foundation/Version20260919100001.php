<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260919100001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E3: versioned global and group sales conditions without demo seeds';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_product', 'mf_catalog_state', 'b_hlbd_mf_group'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('E3 sales conditions require the merged E2 and C3 schema.');
            }
        }
        $groupIdType = $this->integerType('b_hlbd_mf_group', 'ID');
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_sales_conditions (
    ID TINYINT UNSIGNED NOT NULL,
    REVISION BIGINT NOT NULL,
    GIFT_THRESHOLD INT NOT NULL,
    GIFT_FOR_STAFF TINYINT NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    CONSTRAINT ck_mf_sales_conditions CHECK (ID=1 AND REVISION>0 AND GIFT_THRESHOLD>=0 AND GIFT_FOR_STAFF IN (0,1))
) ENGINE=InnoDB
SQL);
        $connection->queryExecute('INSERT INTO mf_sales_conditions(ID,REVISION,GIFT_THRESHOLD,GIFT_FOR_STAFF,UPDATED_AT) VALUES(1,1,0,0,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE ID=ID');
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_group_sales_conditions (
            GROUP_ID {$groupIdType} NOT NULL,
            REVISION BIGINT NOT NULL,
            INHERIT TINYINT NOT NULL,
            GIFT_THRESHOLD INT NOT NULL,
            GIFT_FOR_STAFF TINYINT NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (GROUP_ID),
            CONSTRAINT ck_mf_group_sales_conditions CHECK (GROUP_ID>0 AND REVISION>0 AND INHERIT IN (0,1) AND GIFT_THRESHOLD>=0 AND GIFT_FOR_STAFF IN (0,1)),
            CONSTRAINT fk_mf_group_sales_conditions_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_group_product_condition (
            GROUP_ID {$groupIdType} NOT NULL,
            PRODUCT_UUID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            PRICE INT NOT NULL,
            ACTIVE TINYINT NOT NULL,
            STAFF_DISCOUNT TINYINT NOT NULL,
            PRIMARY KEY (GROUP_ID,PRODUCT_UUID),
            CONSTRAINT ck_mf_group_product_condition CHECK (GROUP_ID>0 AND PRICE>=0 AND ACTIVE IN (0,1) AND STAFF_DISCOUNT IN (0,1)),
            CONSTRAINT fk_mf_group_product_condition_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_group_product_condition_product FOREIGN KEY (PRODUCT_UUID) REFERENCES b_hlbd_mf_product(UF_UUID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_conditions_idempotency (
    ACTOR_ID INT UNSIGNED NOT NULL,
    RESOURCE_KEY VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_KEY CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESULT_REVISION BIGINT NOT NULL,
    RESULT_CATALOG_REVISION BIGINT NOT NULL,
    RESULT_CONDITIONS_REVISION BIGINT NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY),
    KEY ix_mf_conditions_idempotency_created (CREATED_AT),
    CONSTRAINT ck_mf_conditions_idempotency CHECK (ACTOR_ID>0 AND RESULT_REVISION>0 AND RESULT_CATALOG_REVISION>0 AND RESULT_CONDITIONS_REVISION>0)
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_group_product_condition', 'mf_group_sales_conditions', 'mf_conditions_idempotency'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('E3 sales condition data exists; destructive rollback is forbidden.');
            }
        }
        if ($connection->isTableExists('mf_sales_conditions')) {
            $row = $connection->query('SELECT REVISION,GIFT_THRESHOLD,GIFT_FOR_STAFF FROM mf_sales_conditions WHERE ID=1')->fetch();
            if (false !== $row && (1 !== (int)$row['REVISION'] || 0 !== (int)$row['GIFT_THRESHOLD'] || 0 !== (int)$row['GIFT_FOR_STAFF'])) {
                throw new \RuntimeException('Global sales conditions were changed; preserve them when rolling back application code.');
            }
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_group_product_condition');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_group_sales_conditions');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_conditions_idempotency');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_sales_conditions');
    }

    private function integerType(string $table, string $column): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
        $type = false === $row ? '' : strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported parent integer type for ' . $table . '.' . $column);
        }

        return $type;
    }
}
