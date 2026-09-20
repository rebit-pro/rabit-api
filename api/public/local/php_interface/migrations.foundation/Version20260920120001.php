<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260920120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'F1: versioned staff requests, rows, history and idempotency';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_institution', 'b_hlbd_mf_shoot', 'b_hlbd_mf_group', 'mf_media_child'] as $parent) {
            if (!$connection->isTableExists($parent)) {
                throw new \RuntimeException('F1 requires the merged B2, C3 and D2 schema.');
            }
        }
        $institutionType = $this->integerType('b_hlbd_mf_institution', 'ID');
        $shootType = $this->integerType('b_hlbd_mf_shoot', 'ID');
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_staff_request (
            ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            INSTITUTION_ID {$institutionType} NOT NULL,
            SHOOT_ID {$shootType} NOT NULL,
            CREATED_BY INT UNSIGNED NOT NULL,
            CREATED_BY_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            STATUS VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            REVISION BIGINT UNSIGNED NOT NULL,
            COMMENT VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            STAFF_ELIGIBLE TINYINT UNSIGNED NOT NULL,
            ELIGIBILITY_SOURCE VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            ELIGIBILITY_VERIFIED_AT DATETIME NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE KEY ux_mf_staff_request_public (PUBLIC_ID),
            KEY ix_mf_staff_request_scope (INSTITUTION_ID,SHOOT_ID,STATUS,ID),
            KEY ix_mf_staff_request_author (CREATED_BY,STATUS,ID),
            CONSTRAINT ck_mf_staff_request CHECK (CHAR_LENGTH(PUBLIC_ID)=36 AND CREATED_BY>0 AND REVISION>0 AND STATUS IN ('submitted','clarification','transferred') AND STAFF_ELIGIBLE IN (0,1)),
            CONSTRAINT fk_mf_staff_request_institution FOREIGN KEY (INSTITUTION_ID) REFERENCES b_hlbd_mf_institution(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_staff_request_shoot FOREIGN KEY (SHOOT_ID) REFERENCES b_hlbd_mf_shoot(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_staff_request_row (
            ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            REQUEST_ID BIGINT UNSIGNED NOT NULL,
            GROUP_ID {$groupType} NOT NULL,
            CHILD_ID BIGINT UNSIGNED NOT NULL,
            INPUT_CODE VARCHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            PHOTO_IDS_JSON TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            SORT_NO TINYINT UNSIGNED NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            PRIMARY KEY (ID),
            UNIQUE KEY ux_mf_staff_request_row_public (PUBLIC_ID),
            UNIQUE KEY ux_mf_staff_request_sort (REQUEST_ID,SORT_NO),
            KEY ix_mf_staff_request_row_group (GROUP_ID,REQUEST_ID),
            KEY ix_mf_staff_request_child (CHILD_ID,REQUEST_ID),
            CONSTRAINT ck_mf_staff_request_row CHECK (CHAR_LENGTH(PUBLIC_ID)=36 AND INPUT_CODE REGEXP '^[A-Z]{1,3}([0-9]{3})?$' AND SORT_NO BETWEEN 1 AND 30),
            CONSTRAINT fk_mf_staff_request_row_request FOREIGN KEY (REQUEST_ID) REFERENCES mf_staff_request(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_staff_request_row_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_mf_staff_request_row_child FOREIGN KEY (CHILD_ID) REFERENCES mf_media_child(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB");
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_staff_request_history (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    REQUEST_ID BIGINT UNSIGNED NOT NULL,
    KIND VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ACTOR_ID INT UNSIGNED NOT NULL,
    ACTOR_NAME VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    COMMENT VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    CONFIRMED TINYINT UNSIGNED NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    KEY ix_mf_staff_request_history (REQUEST_ID,ID),
    CONSTRAINT ck_mf_staff_request_history CHECK (KIND IN ('submitted','clarification','transferred') AND ACTOR_ID>0 AND CONFIRMED IN (0,1)),
    CONSTRAINT fk_mf_staff_request_history_request FOREIGN KEY (REQUEST_ID) REFERENCES mf_staff_request(ID) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB
SQL);
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_staff_request_idempotency (
    ACTOR_ID INT UNSIGNED NOT NULL,
    RESOURCE_KEY VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_KEY CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PAYLOAD_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RESULT_JSON TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY),
    KEY ix_mf_staff_request_idem_created (CREATED_AT),
    CONSTRAINT ck_mf_staff_request_idem CHECK (ACTOR_ID>0 AND CHAR_LENGTH(IDEMPOTENCY_KEY)=32 AND CHAR_LENGTH(PAYLOAD_HASH)=64)
) ENGINE=InnoDB
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_staff_request_row', 'mf_staff_request_history', 'mf_staff_request_idempotency', 'mf_staff_request'] as $table) {
            if ($connection->isTableExists($table) && false !== $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1')->fetch()) {
                throw new \RuntimeException('F1 handoff data exists; destructive rollback is forbidden.');
            }
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_staff_request_row');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_staff_request_history');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_staff_request_idempotency');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_staff_request');
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
