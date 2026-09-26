<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;

final class Version20260926180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'J1: downloads of purchased originals and ZIP archives; keys of paid orders last until the end of the files month';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_order', 'mf_order_access_key'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('Apply the E5 order migration first: missing ' . $table);
            }
        }
        if (false === $connection->query("SHOW COLUMNS FROM mf_order LIKE 'PAID_AT'")->fetch()) {
            throw new \RuntimeException('Apply the G1 payment migration first: missing mf_order.PAID_AT');
        }
        // ACTIVE_ORDER_ID equals ORDER_ID only while a ZIP is being built: one archive build per order.
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_file_download (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ORDER_ID BIGINT UNSIGNED NOT NULL,
    KIND VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PHOTO_IDS JSON NOT NULL,
    COMPOSITION_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    IDEMPOTENCY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    REQUEST_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ACTIVE_ORDER_ID BIGINT UNSIGNED NULL,
    FILENAME VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ARCHIVE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    BYTES BIGINT UNSIGNED NULL,
    ERROR_CODE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ATTEMPTS TINYINT UNSIGNED NOT NULL DEFAULT 0,
    NEXT_ATTEMPT_AT DATETIME NULL,
    EXPIRES_AT DATETIME NULL,
    READY_AT DATETIME NULL,
    CREATED_AT DATETIME NOT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_file_download_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_file_download_idempotency (ORDER_ID, IDEMPOTENCY_HASH),
    UNIQUE KEY ux_mf_file_download_active (ACTIVE_ORDER_ID),
    KEY ix_mf_file_download_reuse (ORDER_ID, KIND, STATUS, COMPOSITION_HASH),
    KEY ix_mf_file_download_due (STATUS, NEXT_ATTEMPT_AT),
    KEY ix_mf_file_download_expiry (STATUS, EXPIRES_AT),
    CONSTRAINT fk_mf_file_download_order FOREIGN KEY (ORDER_ID) REFERENCES mf_order(ID) ON DELETE RESTRICT,
    CONSTRAINT ck_mf_file_download_values CHECK (
        KIND IN ('file', 'zip')
        AND STATUS IN ('pending', 'ready', 'failed', 'expired')
        AND (ACTIVE_ORDER_ID IS NULL OR (ACTIVE_ORDER_ID = ORDER_ID AND KIND = 'zip' AND STATUS = 'pending'))
        AND (STATUS <> 'ready' OR (EXPIRES_AT IS NOT NULL AND BYTES IS NOT NULL AND (KIND = 'file' OR ARCHIVE_PATH IS NOT NULL)))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // D07/D10: a paid order's key lasts until the end of the files month. Moscow is UTC+3 without DST, and MySQL clamps
        // "+1 MONTH" to the last day of a shorter month exactly like OrderCalendarPolicy::filesAvailableUntil().
        $connection->queryExecute(<<<'SQL'
UPDATE mf_order_access_key k INNER JOIN mf_order o ON o.ID = k.ORDER_ID
SET k.EXPIRES_AT = GREATEST(k.EXPIRES_AT, DATE_SUB(DATE_ADD(DATE_ADD(o.PAID_AT, INTERVAL 3 HOUR), INTERVAL 1 MONTH), INTERVAL 3 HOUR))
WHERE o.PAID_AT IS NOT NULL AND k.REVOKED_AT IS NULL
SQL);
        if (!ModuleManager::isModuleInstalled('morefoto.files')) {
            ModuleManager::registerModule('morefoto.files');
        }
    }

    public function down(): void
    {
        // Downloads are temporary archives: dropping the journal is safe; extended keys stay valid until their new deadline.
        Application::getConnection()->queryExecute('DROP TABLE IF EXISTS mf_file_download');
        if (ModuleManager::isModuleInstalled('morefoto.files')) {
            ModuleManager::unRegisterModule('morefoto.files');
        }
    }
}
