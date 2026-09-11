<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260911130001 extends Version
{
    protected $author = 'codex';
    protected $description = 'W03: durable ownership for new technical Share uploads; no legacy backfill';

    public function up(): void
    {
        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS rebit_share_uploaded_file_owner (
    FILE_ID INT UNSIGNED NOT NULL,
    USER_ID INT UNSIGNED NOT NULL,
    MODULE_ID VARCHAR(50) NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (FILE_ID),
    KEY ix_rebit_share_uploaded_file_owner_user (USER_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('rebit_share_uploaded_file_owner')) {
            return;
        }

        if (false !== $connection->query('SELECT FILE_ID FROM rebit_share_uploaded_file_owner LIMIT 1')->fetch()) {
            throw new \RuntimeException('Ownership table is not empty: preserve it when rolling back application code.');
        }

        $connection->queryExecute('DROP TABLE rebit_share_uploaded_file_owner');
    }
}
