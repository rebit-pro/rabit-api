<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260921130001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E4: stable public identifiers for child-photo assignments';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_photo_assignment')) {
            throw new \RuntimeException('E4 requires the D2 assignment schema.');
        }
        if (false === $connection->query("SHOW COLUMNS FROM mf_photo_assignment LIKE 'PUBLIC_ID'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_photo_assignment ADD PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL');
        }
        $connection->queryExecute('CREATE TABLE IF NOT EXISTS mf_gallery_capability (
            TOKEN_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            GROUP_PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            REVISION BIGINT UNSIGNED NOT NULL,
            REVOKED TINYINT UNSIGNED NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            KEY ix_mf_gallery_group (GROUP_PUBLIC_ID),
            CONSTRAINT fk_mf_gallery_group FOREIGN KEY (GROUP_PUBLIC_ID) REFERENCES b_hlbd_mf_group(UF_PUBLIC_ID) ON DELETE RESTRICT,
            CONSTRAINT ck_mf_gallery_key CHECK (REVISION>0 AND REVOKED IN (0,1))
        ) ENGINE=InnoDB');
        $connection->queryExecute('UPDATE mf_photo_assignment SET PUBLIC_ID=UUID() WHERE PUBLIC_ID IS NULL');
        $connection->queryExecute('ALTER TABLE mf_photo_assignment MODIFY PUBLIC_ID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
        if (false === $connection->query("SHOW INDEX FROM mf_photo_assignment WHERE Key_name='ux_mf_assignment_public'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_photo_assignment ADD UNIQUE KEY ux_mf_assignment_public (PUBLIC_ID)');
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Assignment IDs may be referenced by quotes; preserve them when rolling back application code.');
    }
}
