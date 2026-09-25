<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260923120001 extends Version
{
    private const string TABLE = 'b_rebit_notification_operation';

    protected $author = 'codex';
    protected $description = 'B4: optional HTML body for outgoing email operations';

    public function up(): void
    {
        $connection = Application::getConnection();
        if (!$this->hasColumn()) {
            $connection->queryExecute(
                'ALTER TABLE ' . self::TABLE
                . ' ADD COLUMN BODY_HTML MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER BODY',
            );
        }
    }

    public function down(): void
    {
        // The plain-text BODY stays; only the optional HTML variant is dropped.
        if ($this->hasColumn()) {
            Application::getConnection()->queryExecute('ALTER TABLE ' . self::TABLE . ' DROP COLUMN BODY_HTML');
        }
    }

    private function hasColumn(): bool
    {
        $connection = Application::getConnection();

        return $connection->isTableExists(self::TABLE)
            && false !== $connection->query('SHOW COLUMNS FROM ' . self::TABLE . " LIKE 'BODY_HTML'")->fetch();
    }
}
