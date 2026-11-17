<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261117180001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Сохраняемые служебные ZIP купленных электронных кадров учреждения';

    public function up(): void
    {
        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_institution_digital_archive (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    INSTITUTION_ID INT UNSIGNED NOT NULL,
    INSTITUTION_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ACTOR_ID INT UNSIGNED NOT NULL,
    KEY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CAPTURED_AT DATETIME NOT NULL,
    SNAPSHOT JSON NOT NULL,
    STATUS VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ATTEMPTS TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LEASE_OWNER CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    NEXT_AT DATETIME NULL,
    WORK_PATHS JSON NOT NULL,
    ARCHIVE_PATH VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
    BYTES BIGINT UNSIGNED NULL,
    ERROR_CODE VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
    READY_AT DATETIME NULL,
    EXPIRES_AT DATETIME NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mf_digital_archive_public (PUBLIC_ID),
    UNIQUE KEY ux_mf_digital_archive_request (ACTOR_ID,KEY_HASH),
    KEY ix_mf_digital_archive_institution (INSTITUTION_ID,ID),
    KEY ix_mf_digital_archive_due (STATUS,NEXT_AT),
    KEY ix_mf_digital_archive_expiry (STATUS,EXPIRES_AT),
    CONSTRAINT ck_mf_digital_archive_state CHECK (
        STATUS IN ('pending','ready','failed','empty','expired') AND ATTEMPTS <= 3
        AND (STATUS <> 'ready' OR (ARCHIVE_PATH IS NOT NULL AND BYTES > 0 AND READY_AT IS NOT NULL AND EXPIRES_AT IS NOT NULL))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $db = Application::getConnection();
        if ($db->isTableExists('mf_institution_digital_archive') && false !== $db->query('SELECT ID FROM mf_institution_digital_archive LIMIT 1')->fetch()) {
            throw new \RuntimeException('Состав электронных архивов уже сохранён; разрушительный откат запрещён.');
        }
        $db->queryExecute('DROP TABLE IF EXISTS mf_institution_digital_archive');
    }
}
