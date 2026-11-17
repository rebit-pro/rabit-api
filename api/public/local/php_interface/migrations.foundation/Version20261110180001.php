<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261110180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Institution print ZIPs: immutable references to L1 jobs, file leases and staff requests';

    public function up(): void
    {
        $db = Application::getConnection();
        if (!$db->isTableExists('mf_production_version') || false === $db->query("SHOW COLUMNS FROM mf_production_version LIKE 'LAYOUT_VERSION'")->fetch()) {
            throw new \RuntimeException('Apply the L1 and print layout v2 migrations first.');
        }
        $db->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_institution_archive (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    INSTITUTION_ID INT UNSIGNED NOT NULL,
    INSTITUTION_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    VERSION_NO INT UNSIGNED NOT NULL,
    KIND VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    FINGERPRINT CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    SNAPSHOT JSON NOT NULL,
    LINES_COUNT INT UNSIGNED NOT NULL,
    FILES_COUNT INT UNSIGNED NOT NULL,
    PRINTS_COUNT INT UNSIGNED NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ERROR_CODE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ATTEMPTS TINYINT UNSIGNED NOT NULL DEFAULT 0,
    GENERATION INT UNSIGNED NOT NULL DEFAULT 1,
    LEASE_OWNER CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    LEASE_UNTIL DATETIME NULL,
    WORK_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    OBSOLETE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ARCHIVE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    BYTES BIGINT UNSIGNED NULL,
    CREATED_AT DATETIME NOT NULL,
    READY_AT DATETIME NULL,
    FIRST_READY_AT DATETIME NULL,
    EXPIRES_AT DATETIME NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_institution_archive_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_institution_archive_no (INSTITUTION_ID,VERSION_NO),
    UNIQUE KEY ux_mf_institution_archive_snapshot (INSTITUTION_ID,KIND,FINGERPRINT),
    KEY ix_mf_institution_archive_due (STATUS,LEASE_UNTIL),
    KEY ix_mf_institution_archive_expiry (STATUS,EXPIRES_AT),
    CONSTRAINT ck_mf_institution_archive_values CHECK (
        KIND IN ('full','supplement') AND VERSION_NO >= 1 AND GENERATION >= 1
        AND STATUS IN ('pending','ready','failed','empty','expired')
        AND (STATUS <> 'ready' OR (ARCHIVE_PATH IS NOT NULL AND BYTES IS NOT NULL AND READY_AT IS NOT NULL AND EXPIRES_AT IS NOT NULL))
        AND ((STATUS = 'empty' AND LINES_COUNT = 0) OR (STATUS <> 'empty' AND LINES_COUNT > 0))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        if (false === $db->query("SHOW COLUMNS FROM mf_production_institution_archive LIKE 'FIRST_READY_AT'")->fetch()) {
            $db->queryExecute('ALTER TABLE mf_production_institution_archive ADD FIRST_READY_AT DATETIME NULL');
        }
        if (false === $db->query("SHOW COLUMNS FROM mf_production_institution_archive LIKE 'OBSOLETE_PATH'")->fetch()) {
            $db->queryExecute('ALTER TABLE mf_production_institution_archive ADD OBSOLETE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL');
        }
        $db->queryExecute('UPDATE mf_production_institution_archive SET FIRST_READY_AT=READY_AT WHERE FIRST_READY_AT IS NULL AND READY_AT IS NOT NULL');
        $db->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_institution_request (
    ACTOR_ID INT UNSIGNED NOT NULL,
    KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    REQUEST_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ARCHIVE_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (ACTOR_ID,KEY_HASH),
    CONSTRAINT fk_mf_institution_request_archive FOREIGN KEY (ARCHIVE_PUBLIC_ID)
        REFERENCES mf_production_institution_archive(PUBLIC_ID) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $db->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_production_institution_state (
    INSTITUTION_ID INT UNSIGNED NOT NULL,
    ERROR_CODE VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (INSTITUTION_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        if (false === $db->query("SHOW COLUMNS FROM mf_production_schedule LIKE 'INSTITUTION_CURSOR_ID'")->fetch()) {
            $db->queryExecute('ALTER TABLE mf_production_schedule ADD INSTITUTION_CURSOR_ID INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        $db = Application::getConnection();
        if ($db->isTableExists('mf_production_institution_archive') && false !== $db->query('SELECT ID FROM mf_production_institution_archive LIMIT 1')->fetch()) {
            throw new \RuntimeException('Institution archive snapshots exist; destructive rollback is forbidden.');
        }
        foreach (['mf_production_institution_request', 'mf_production_institution_archive', 'mf_production_institution_state'] as $table) {
            $db->queryExecute('DROP TABLE IF EXISTS ' . $table);
        }
        if (false !== $db->query("SHOW COLUMNS FROM mf_production_schedule LIKE 'INSTITUTION_CURSOR_ID'")->fetch()) {
            $db->queryExecute('ALTER TABLE mf_production_schedule DROP COLUMN INSTITUTION_CURSOR_ID');
        }
    }
}
