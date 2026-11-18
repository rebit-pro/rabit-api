<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261118120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Приватная ссылка на каталог групп одной съёмки';

    public function up(): void
    {
        Application::getConnection()->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_shoot_gallery_link (
    SHOOT_PUBLIC_ID CHAR(36) NOT NULL PRIMARY KEY,
    ACCESS_KEY CHAR(32) NOT NULL UNIQUE,
    REVOKED TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin
SQL);
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('mf_shoot_gallery_link') && false !== $connection->query('SELECT 1 FROM mf_shoot_gallery_link LIMIT 1')->fetch()) {
            throw new \RuntimeException('Нельзя удалить выданные ссылки съёмок.');
        }
        $connection->queryExecute('DROP TABLE IF EXISTS mf_shoot_gallery_link');
    }
}
