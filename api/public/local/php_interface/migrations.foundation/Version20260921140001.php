<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260921140001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E4: expiring server cart quotes';

    public function up(): void
    {
        Application::getConnection()->queryExecute('CREATE TABLE IF NOT EXISTS mf_cart_quote (
            TOKEN_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            GALLERY_HASH CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            FINGERPRINT CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            SNAPSHOT_JSON MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            EXPIRES_AT DATETIME NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            KEY ix_mf_quote_expiry (EXPIRES_AT),
            CONSTRAINT fk_mf_quote_gallery FOREIGN KEY (GALLERY_HASH) REFERENCES mf_gallery_capability(TOKEN_HASH) ON DELETE RESTRICT
        ) ENGINE=InnoDB');
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (false !== $connection->query('SELECT TOKEN_HASH FROM mf_cart_quote LIMIT 1')->fetch()) {
            throw new \RuntimeException('Cart quotes exist; preserve them when rolling back application code.');
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_cart_quote');
    }
}
