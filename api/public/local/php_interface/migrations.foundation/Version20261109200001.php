<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261109200001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Print archive layout v2: frozen original filenames and quantity directories';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_production_version', 'mf_production_line'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('Apply the L1 production migration first.');
            }
        }
        if (false === $connection->query("SHOW COLUMNS FROM mf_production_version LIKE 'LAYOUT_VERSION'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_production_version ADD LAYOUT_VERSION TINYINT UNSIGNED NOT NULL DEFAULT 1');
        }
        $row = $connection->query("SHOW COLUMNS FROM mf_production_line LIKE 'ENTRY'")->fetch();
        if (false !== $row && 'varchar(512)' !== strtolower((string)$row['Type'])) {
            $connection->queryExecute('ALTER TABLE mf_production_line MODIFY ENTRY VARCHAR(512) NOT NULL');
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (false !== $connection->query("SHOW COLUMNS FROM mf_production_version LIKE 'LAYOUT_VERSION'")->fetch()) {
            if (false !== $connection->query('SELECT ID FROM mf_production_version WHERE LAYOUT_VERSION<>1 LIMIT 1')->fetch()) {
                throw new \RuntimeException('New print layouts exist; destructive rollback is forbidden.');
            }
        }
        if (false !== $connection->query('SELECT ID FROM mf_production_line WHERE CHAR_LENGTH(ENTRY)>255 LIMIT 1')->fetch()) {
            throw new \RuntimeException('Long archive entries exist; destructive rollback is forbidden.');
        }
        $connection->queryExecute('ALTER TABLE mf_production_line MODIFY ENTRY VARCHAR(255) NOT NULL');
        if (false !== $connection->query("SHOW COLUMNS FROM mf_production_version LIKE 'LAYOUT_VERSION'")->fetch()) {
            $connection->queryExecute('ALTER TABLE mf_production_version DROP COLUMN LAYOUT_VERSION');
        }
    }
}
