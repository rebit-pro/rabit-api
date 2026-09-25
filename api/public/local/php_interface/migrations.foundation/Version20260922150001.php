<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260922150001 extends Version
{
    protected $author = 'codex';
    protected $description = 'F2: retrievable gallery link, group link preparation, history and idempotency';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_group', 'mf_gallery_capability', 'mf_staff_request'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('F2 requires the merged C3, F1 and E4 schema.');
            }
        }
        if (false === $connection->query("SHOW COLUMNS FROM mf_gallery_capability LIKE 'TOKEN'")->fetch()) {
            // Decision F2 (2026-09-22): staff re-read the raw link; the hash stays the lookup key.
            $connection->queryExecute('ALTER TABLE mf_gallery_capability ADD TOKEN CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER TOKEN_HASH');
        }
        if (false === $connection->query("SHOW INDEX FROM mf_gallery_capability WHERE Key_name='ix_mf_gallery_group_active'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_gallery_capability ADD KEY ix_mf_gallery_group_active (GROUP_PUBLIC_ID,REVOKED,CREATED_AT)');
        }
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_group_link (
            GROUP_ID {$groupType} NOT NULL,
            REVISION BIGINT UNSIGNED NOT NULL,
            PREPARED_SIGNATURE CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            PREPARED_AT DATETIME NULL,
            PREPARED_BY INT UNSIGNED NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (GROUP_ID),
            CONSTRAINT ck_mf_group_link CHECK (REVISION>1 AND ((PREPARED_SIGNATURE IS NULL AND PREPARED_AT IS NULL AND PREPARED_BY IS NULL)
                OR (CHAR_LENGTH(PREPARED_SIGNATURE)=64 AND PREPARED_AT IS NOT NULL AND PREPARED_BY>0))),
            CONSTRAINT fk_mf_group_link_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_group_link_history (
            ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            GROUP_ID {$groupType} NOT NULL,
            KIND VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            ACTOR_ID INT UNSIGNED NOT NULL,
            ACTOR_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            SIGNATURE CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            SENT_AT DATETIME NULL,
            CLOSES_AT DATETIME NULL,
            DELIVERY_DUE_AT DATETIME NULL,
            PREVIOUS_SENT_AT DATETIME NULL,
            PREVIOUS_CLOSES_AT DATETIME NULL,
            PREVIOUS_DELIVERY_DUE_AT DATETIME NULL,
            REASON VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            CREATED_AT DATETIME NOT NULL,
            PRIMARY KEY (ID),
            KEY ix_mf_group_link_history (GROUP_ID,ID),
            CONSTRAINT ck_mf_group_link_history CHECK (ACTOR_ID>0 AND (
                (KIND='prepared' AND CHAR_LENGTH(SIGNATURE)=64 AND SENT_AT IS NULL AND REASON IS NULL)
                OR (KIND='transmitted' AND SENT_AT IS NOT NULL AND CLOSES_AT IS NOT NULL AND DELIVERY_DUE_AT IS NOT NULL AND PREVIOUS_SENT_AT IS NULL AND REASON IS NULL)
                OR (KIND='corrected' AND SENT_AT IS NOT NULL AND CLOSES_AT IS NOT NULL AND DELIVERY_DUE_AT IS NOT NULL
                    AND PREVIOUS_SENT_AT IS NOT NULL AND PREVIOUS_CLOSES_AT IS NOT NULL AND PREVIOUS_DELIVERY_DUE_AT IS NOT NULL AND CHAR_LENGTH(REASON) BETWEEN 5 AND 500))),
            CONSTRAINT fk_mf_group_link_history_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_group_link_idempotency (
    ACTOR_ID INT UNSIGNED NOT NULL,
    RESOURCE_KEY VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_KEY CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESULT_JSON TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY),
    KEY ix_mf_group_link_idem_created (CREATED_AT),
    CONSTRAINT ck_mf_group_link_idem CHECK (ACTOR_ID>0 AND CHAR_LENGTH(IDEMPOTENCY_KEY)=32 AND CHAR_LENGTH(PAYLOAD_HASH)=64)
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_group_link', 'mf_group_link_history', 'mf_group_link_idempotency'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('F2 link data exists; destructive rollback is forbidden.');
            }
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_group_link_idempotency');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_group_link_history');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_group_link');
        if (false !== $connection->query("SHOW INDEX FROM mf_gallery_capability WHERE Key_name='ix_mf_gallery_group_active'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_gallery_capability DROP KEY ix_mf_gallery_group_active');
        }
        // Hash lookups keep issued links working; only the staff copy of the raw key disappears.
        if (false !== $connection->query("SHOW COLUMNS FROM mf_gallery_capability LIKE 'TOKEN'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_gallery_capability DROP COLUMN TOKEN');
        }
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
