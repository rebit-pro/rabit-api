<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260923120002 extends Version
{
    private const string TABLE = 'rebit_auth_access_link';

    protected $author = 'codex';
    protected $description = 'B4: personal invitation and password reset links (token stored as SHA-256)';

    public function up(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists(self::TABLE)) {
            return;
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE rebit_auth_access_link (
    ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    USER_ID INT NOT NULL,
    PURPOSE VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    TOKEN_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ISSUED_AT DATETIME NOT NULL,
    EXPIRES_AT DATETIME NOT NULL,
    RESEND_AVAILABLE_AT DATETIME NOT NULL,
    USED_AT DATETIME NULL,
    ISSUED_BY INT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_rebit_auth_access_link_user (USER_ID, PURPOSE),
    UNIQUE KEY ux_rebit_auth_access_link_token (TOKEN_HASH),
    CONSTRAINT ck_rebit_auth_access_link_purpose CHECK (PURPOSE IN ('invite','reset'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        // Unused links only: issued invitations and resets must not disappear silently.
        if ($connection->isTableExists(self::TABLE)
            && false === $connection->query('SELECT ID FROM ' . self::TABLE . ' LIMIT 1')->fetch()) {
            $connection->queryExecute('DROP TABLE ' . self::TABLE);
        }
    }
}
