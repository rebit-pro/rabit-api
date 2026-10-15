<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20261015120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'L1: archive of paid prints — versions, their order lines, the schedule cursor and staff idempotency keys';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_order', 'mf_order_line', 'b_hlbd_mf_group'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('Apply the E5 order and the Organization migrations first: missing ' . $table);
            }
        }
        if (false === $connection->query("SHOW COLUMNS FROM mf_order LIKE 'LATE_PAYMENT'")->fetch()) {
            throw new \RuntimeException('Apply the G1 payment migration first: missing mf_order.LATE_PAYMENT');
        }
        // L1-DEC-08: groups whose print closed before this release are built only by the organizer's button.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_schedule (
    ID TINYINT UNSIGNED NOT NULL,
    SINCE_AT DATETIME NOT NULL,
    CURSOR_AT DATETIME NOT NULL,
    CURSOR_GROUP_ID INT UNSIGNED NOT NULL DEFAULT 0,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    CONSTRAINT ck_mf_production_schedule_single CHECK (ID = 1 AND CURSOR_AT >= SINCE_AT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $connection->queryExecute('INSERT IGNORE INTO mf_production_schedule(ID,SINCE_AT,CURSOR_AT,CURSOR_GROUP_ID,UPDATED_AT)
            VALUES(1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),0,UTC_TIMESTAMP())');
        // GROUP_ID is the native Organization group: a version belongs to one print deadline of one group.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_version (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    GROUP_ID INT UNSIGNED NOT NULL,
    GROUP_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    INSTITUTION_ID INT UNSIGNED NOT NULL,
    DEADLINE_AT DATETIME NOT NULL,
    VERSION_NO SMALLINT UNSIGNED NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    LINES_COUNT INT UNSIGNED NOT NULL,
    FILES_COUNT INT UNSIGNED NOT NULL,
    PRINTS_COUNT INT UNSIGNED NOT NULL,
    WAITING_ORDERS INT UNSIGNED NOT NULL DEFAULT 0,
    ARCHIVE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    BYTES BIGINT UNSIGNED NULL,
    ERROR_CODE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ATTEMPTS TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LEASE_UNTIL DATETIME NULL,
    LINK_GENERATION INT UNSIGNED NOT NULL DEFAULT 1,
    LINK_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    READY_AT DATETIME NULL,
    EXPIRES_AT DATETIME NULL,
    CREATED_BY INT UNSIGNED NULL,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_production_version_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_production_version_no (GROUP_ID, DEADLINE_AT, VERSION_NO),
    UNIQUE KEY ux_mf_production_version_link (LINK_HASH),
    KEY ix_mf_production_version_due (STATUS, LEASE_UNTIL),
    KEY ix_mf_production_version_expiry (STATUS, EXPIRES_AT),
    CONSTRAINT ck_mf_production_version_values CHECK (
        STATUS IN ('pending', 'ready', 'failed', 'empty', 'expired')
        AND VERSION_NO >= 1 AND LINK_GENERATION >= 1
        AND (STATUS <> 'ready' OR (LINK_HASH IS NOT NULL AND BYTES IS NOT NULL AND READY_AT IS NOT NULL AND EXPIRES_AT IS NOT NULL))
        AND (STATUS = 'ready' OR LINK_HASH IS NULL)
        AND (STATUS <> 'empty' OR (LINES_COUNT = 0 AND VERSION_NO = 1))
        AND (STATUS = 'empty' OR LINES_COUNT > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // An order line enters exactly one version of its group's deadline: a supplement takes only new lines.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_line (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    VERSION_ID BIGINT UNSIGNED NOT NULL,
    GROUP_ID INT UNSIGNED NOT NULL,
    DEADLINE_AT DATETIME NOT NULL,
    ORDER_LINE_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_NUMBER VARCHAR(32) NOT NULL,
    PAID_AT DATETIME NOT NULL,
    AUDIENCE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CHILD_CODE VARCHAR(32) NOT NULL,
    PHOTO_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PHOTO_CODE VARCHAR(64) NOT NULL,
    PRODUCT_PUBLIC_ID VARCHAR(64) NOT NULL,
    PRODUCT_NAME VARCHAR(255) NOT NULL,
    PRODUCT_FORMAT VARCHAR(100) NOT NULL,
    PRINT_COUNT SMALLINT UNSIGNED NOT NULL,
    QUANTITY SMALLINT UNSIGNED NOT NULL,
    PRINTS INT UNSIGNED NOT NULL,
    ENTRY VARCHAR(255) NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_production_line_order (GROUP_ID, DEADLINE_AT, ORDER_LINE_ID),
    KEY ix_mf_production_line_version (VERSION_ID, ENTRY),
    CONSTRAINT fk_mf_production_line_version FOREIGN KEY (VERSION_ID) REFERENCES mf_production_version(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_production_line_prints CHECK (QUANTITY >= 1 AND PRINT_COUNT >= 1 AND PRINTS = QUANTITY * PRINT_COUNT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_request (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ACTOR_ID INT UNSIGNED NOT NULL,
    KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    REQUEST_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    VERSION_ID BIGINT UNSIGNED NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_production_request_key (ACTOR_ID, KEY_HASH),
    CONSTRAINT fk_mf_production_request_version FOREIGN KEY (VERSION_ID) REFERENCES mf_production_version(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        if (!ModuleManager::isModuleInstalled('morefoto.production')) {
            ModuleManager::registerModule('morefoto.production');
        }
    }

    public function down(): void
    {
        // Archive files stay on disk under production/; the next up starts a fresh schedule from its own moment.
        foreach (['mf_production_request', 'mf_production_line', 'mf_production_version', 'mf_production_schedule'] as $table) {
            Application::getConnection()->queryExecute('DROP TABLE IF EXISTS ' . $table);
        }
        if (ModuleManager::isModuleInstalled('morefoto.production')) {
            ModuleManager::unRegisterModule('morefoto.production');
        }
    }
}
