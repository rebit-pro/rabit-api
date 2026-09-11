<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

final class Version20260911220001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E1: independent MoreFoto Product HL catalogue and global revision; no products seeded';

    public function up(): void
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Install highloadblock before applying the catalogue migration.');
        }
        $helper = $this->getHelperManager()->Hlblock();
        $id = $helper->saveHlblock(['NAME' => 'MfProduct', 'TABLE_NAME' => 'b_hlbd_mf_product']);
        /** @var array<string, int> $strings */
        $strings = ['UF_UUID' => 36, 'UF_NAME' => 255, 'UF_DESCRIPTION' => 4000, 'UF_KIND' => 16, 'UF_FORMAT' => 100, 'UF_UNIT' => 100];
        foreach ($strings as $name => $length) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name, 'USER_TYPE_ID' => 'string', 'MULTIPLE' => 'N',
                'MANDATORY' => in_array($name, ['UF_UUID', 'UF_NAME', 'UF_KIND'], true) ? 'Y' : 'N',
                'SETTINGS' => ['SIZE' => 60, 'ROWS' => 1, 'MIN_LENGTH' => 0, 'MAX_LENGTH' => $length, 'DEFAULT_VALUE' => ''],
            ]);
        }
        foreach (['UF_PRICE', 'UF_PRINT_COUNT'] as $name) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name, 'USER_TYPE_ID' => 'integer', 'MULTIPLE' => 'N', 'MANDATORY' => 'N',
                'SETTINGS' => ['MIN_VALUE' => 0, 'MAX_VALUE' => 2147483647, 'DEFAULT_VALUE' => 0],
            ]);
        }
        foreach (['UF_STAFF_DISCOUNT', 'UF_ACTIVE'] as $name) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name, 'USER_TYPE_ID' => 'boolean', 'MULTIPLE' => 'N', 'MANDATORY' => 'N',
                'SETTINGS' => ['DEFAULT_VALUE' => 0, 'DISPLAY' => 'CHECKBOX'],
            ]);
        }
        foreach (['UF_CREATED_AT', 'UF_UPDATED_AT'] as $name) {
            $helper->saveField($id, [
                'FIELD_NAME' => $name, 'USER_TYPE_ID' => 'datetime', 'MULTIPLE' => 'N', 'MANDATORY' => 'Y',
                'SETTINGS' => ['DEFAULT_VALUE' => ['TYPE' => 'NONE', 'VALUE' => ''], 'USE_SECOND' => 'Y', 'USE_TIMEZONE' => 'N'],
            ]);
        }
        $connection = Application::getConnection();
        // Match database constraints to the typed application data; preserve existing rows on re-run.
        $connection->queryExecute('ALTER TABLE b_hlbd_mf_product ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection->queryExecute(<<<'SQL'
ALTER TABLE b_hlbd_mf_product
    MODIFY UF_UUID CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY UF_NAME VARCHAR(255) NOT NULL,
    MODIFY UF_DESCRIPTION TEXT NOT NULL,
    MODIFY UF_KIND VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY UF_PRICE INT NOT NULL,
    MODIFY UF_PRINT_COUNT INT NOT NULL,
    MODIFY UF_FORMAT VARCHAR(100) NOT NULL,
    MODIFY UF_UNIT VARCHAR(100) NOT NULL,
    MODIFY UF_STAFF_DISCOUNT TINYINT NOT NULL,
    MODIFY UF_ACTIVE TINYINT NOT NULL,
    MODIFY UF_CREATED_AT DATETIME NOT NULL,
    MODIFY UF_UPDATED_AT DATETIME NOT NULL
SQL);
        if (false === $connection->query("SHOW INDEX FROM b_hlbd_mf_product WHERE Key_name = 'ux_mf_product_uuid'")->fetch()) {
            $connection->queryExecute('ALTER TABLE b_hlbd_mf_product ADD UNIQUE KEY ux_mf_product_uuid (UF_UUID)');
        }
        if (false === $connection->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'b_hlbd_mf_product' AND CONSTRAINT_NAME = 'ck_mf_product_values'")->fetch()) {
            $connection->queryExecute(<<<'SQL'
ALTER TABLE b_hlbd_mf_product ADD CONSTRAINT ck_mf_product_values CHECK (
    UF_PRICE >= 0 AND UF_PRINT_COUNT >= 0 AND UF_STAFF_DISCOUNT IN (0, 1) AND UF_ACTIVE IN (0, 1)
    AND UF_KIND IN ('physical', 'digital', 'bundle') AND CHAR_LENGTH(UF_DESCRIPTION) <= 4000
    AND CHAR_LENGTH(TRIM(UF_NAME)) > 0
)
SQL);
        }
        $connection->queryExecute(<<<'SQL'
CREATE TABLE IF NOT EXISTS mf_catalog_state (
    ID TINYINT UNSIGNED NOT NULL,
    REVISION BIGINT NOT NULL,
    PRIMARY KEY (ID),
    CONSTRAINT ck_mf_catalog_state CHECK (ID = 1 AND REVISION > 0)
) ENGINE=InnoDB
SQL);
        $connection->queryExecute('INSERT INTO mf_catalog_state (ID, REVISION) VALUES (1, 1) ON DUPLICATE KEY UPDATE ID = ID');
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($connection->isTableExists('b_hlbd_mf_product') && false !== $connection->query('SELECT ID FROM b_hlbd_mf_product LIMIT 1')->fetch()) {
            throw new \RuntimeException('Catalogue contains products. Preserve data when rolling back application code.');
        }
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Highloadblock is required for catalogue rollback.');
        }
        $this->getHelperManager()->Hlblock()->deleteHlblockIfExists('MfProduct');
        $connection->queryExecute('DROP TABLE IF EXISTS mf_catalog_state');
    }
}
