<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260923120003 extends Version
{
    private const string TABLE = 'mf_staff_avatar';

    protected $author = 'codex';
    protected $description = 'B3: staff avatar versions (files are private WebP next to the media originals)';

    public function up(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists(self::TABLE)) {
            return;
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE mf_staff_avatar (
    USER_ID INT NOT NULL,
    VERSION INT UNSIGNED NOT NULL,
    FINGERPRINT CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MIME VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    BYTES INT UNSIGNED NOT NULL,
    WIDTH INT UNSIGNED NOT NULL,
    HEIGHT INT UNSIGNED NOT NULL,
    UPDATED_BY INT NULL,
    UPDATED_AT DATETIME NOT NULL,
    PRIMARY KEY (USER_ID),
    CONSTRAINT ck_mf_staff_avatar_version CHECK (VERSION > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // Only an unused table goes: uploaded avatars must not disappear silently.
        if ($connection->isTableExists(self::TABLE)
            && false === $connection->query('SELECT USER_ID FROM ' . self::TABLE . ' LIMIT 1')->fetch()) {
            $connection->queryExecute('DROP TABLE ' . self::TABLE);
        }
    }
}
