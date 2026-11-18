<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261118213001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Разрешение выбора количества электронного файла в каталоге';

    public function up(): void
    {
        $db = Application::getConnection();
        if ($this->hasCatalogCheck()) {
            return;
        }
        $helper = $this->getHelperManager()->Hlblock();
        $helper->saveField($helper->getHlblockId('MfProduct'), [
            'FIELD_NAME' => 'UF_ALLOW_DIGITAL_QUANTITY', 'USER_TYPE_ID' => 'boolean', 'MULTIPLE' => 'N', 'MANDATORY' => 'N',
            'SETTINGS' => ['DEFAULT_VALUE' => 0, 'DISPLAY' => 'CHECKBOX'],
        ]);
        $db->queryExecute('UPDATE b_hlbd_mf_product SET UF_ALLOW_DIGITAL_QUANTITY = 0 WHERE UF_ALLOW_DIGITAL_QUANTITY IS NULL');
        $db->queryExecute('ALTER TABLE b_hlbd_mf_product MODIFY UF_ALLOW_DIGITAL_QUANTITY TINYINT UNSIGNED NOT NULL DEFAULT 0,'
            . ' ADD CONSTRAINT ck_mf_product_digital_quantity CHECK (UF_ALLOW_DIGITAL_QUANTITY = 0'
            . " OR (UF_KIND = 'digital' AND UF_ALLOW_DIGITAL_QUANTITY = 1))");
    }

    public function down(): void
    {
        $db = Application::getConnection();
        $helper = $this->getHelperManager()->Hlblock();
        if (false === $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_product'"
            . " AND COLUMN_NAME='UF_ALLOW_DIGITAL_QUANTITY'")->fetch()) {
            return;
        }
        if (false !== $db->query('SELECT 1 FROM b_hlbd_mf_product WHERE UF_ALLOW_DIGITAL_QUANTITY = 1 LIMIT 1')->fetch()) {
            throw new \RuntimeException('Сначала отключите выбор количества для электронных товаров каталога.');
        }
        if ($this->hasCatalogCheck()) {
            $db->queryExecute('ALTER TABLE b_hlbd_mf_product DROP CHECK ck_mf_product_digital_quantity');
        }
        $helper->deleteField('MfProduct', 'UF_ALLOW_DIGITAL_QUANTITY');
    }

    private function hasCatalogCheck(): bool
    {
        return false !== Application::getConnection()->query('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE()'
            . " AND TABLE_NAME = 'b_hlbd_mf_product' AND CONSTRAINT_NAME = 'ck_mf_product_digital_quantity'")->fetch();
    }
}
