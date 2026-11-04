<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261104100001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Issue 232: scheduled gallery opening and separate manual link delivery';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_group_link')) {
            throw new \RuntimeException('Scheduled openings require the merged F2 link schema.');
        }
        $groupType = $this->integerType('b_hlbd_mf_group', 'ID');
        $connection->queryExecute("CREATE TABLE IF NOT EXISTS mf_gallery_opening (
            GROUP_ID {$groupType} NOT NULL,
            OPEN_AT DATETIME NOT NULL,
            STATUS VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            SCHEDULED_BY INT UNSIGNED NOT NULL,
            OPENED_AT DATETIME NULL,
            MANUAL_SENT_AT DATETIME NULL,
            MANUAL_SENT_BY INT UNSIGNED NULL,
            CREATED_AT DATETIME NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            PRIMARY KEY (GROUP_ID),
            KEY ix_mf_gallery_opening_due (STATUS,OPEN_AT,GROUP_ID),
            CONSTRAINT fk_mf_gallery_opening_group FOREIGN KEY (GROUP_ID) REFERENCES b_hlbd_mf_group(ID) ON DELETE CASCADE,
            CONSTRAINT ck_mf_gallery_opening_status CHECK (STATUS IN ('scheduled','opened','blocked','cancelled')),
            CONSTRAINT ck_mf_gallery_opening_opened CHECK ((STATUS='opened' AND OPENED_AT IS NOT NULL) OR (STATUS<>'opened' AND OPENED_AT IS NULL)),
            CONSTRAINT ck_mf_gallery_opening_manual CHECK ((MANUAL_SENT_AT IS NULL AND MANUAL_SENT_BY IS NULL) OR (MANUAL_SENT_AT IS NOT NULL AND MANUAL_SENT_BY>0))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_gallery_opening')) {
            return;
        }
        if (false !== $connection->query('SELECT GROUP_ID FROM mf_gallery_opening LIMIT 1')->fetch()) {
            throw new \RuntimeException('Scheduled openings exist; destructive rollback is forbidden.');
        }
        $connection->queryExecute('DROP TABLE mf_gallery_opening');
    }

    private function integerType(string $table, string $column): string
    {
        $row = Application::getConnection()->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
        $type = false === $row ? '' : strtolower((string)$row['COLUMN_TYPE']);
        if (1 !== preg_match('/^(?:big)?int(?:\([0-9]+\))?(?: unsigned)?$/D', $type)) {
            throw new \RuntimeException('Unsupported parent integer type for ' . $table . '.' . $column);
        }

        return $type;
    }
}
