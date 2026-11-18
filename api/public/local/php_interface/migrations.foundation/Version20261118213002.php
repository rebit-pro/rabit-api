<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20261118213002 extends Version
{
    private const string LINE_VALUES = "PRODUCT_KIND IN ('physical', 'digital', 'bundle') AND QUANTITY BETWEEN 1 AND 99"
        . " AND ((PRODUCT_KIND = 'bundle') = (PHOTO_PUBLIC_ID IS NULL))"
        . " AND STAFF_DISCOUNT IN (0, 1) AND COVERED_BY_GIFT IN (0, 1) AND CHILD_CODE REGEXP '^[A-Z]{1,3}$'";

    protected $author = 'codex';
    protected $description = 'Снимок разрешения количества электронного файла в заказе';

    public function up(): void
    {
        $db = Application::getConnection();
        if (!$this->hasOrderFlag()) {
            $db->queryExecute('ALTER TABLE mf_order_line ADD ALLOW_DIGITAL_QUANTITY TINYINT UNSIGNED NOT NULL DEFAULT 0,'
                . ' DROP CHECK ck_mf_order_line_values, ADD CONSTRAINT ck_mf_order_line_values CHECK (' . self::LINE_VALUES
                . " AND (ALLOW_DIGITAL_QUANTITY = 0 OR (PRODUCT_KIND = 'digital' AND ALLOW_DIGITAL_QUANTITY = 1))"
                . " AND (PRODUCT_KIND = 'physical' OR ALLOW_DIGITAL_QUANTITY = 1 OR QUANTITY = 1))");
        }
    }

    public function down(): void
    {
        $db = Application::getConnection();
        if ($this->hasOrderFlag()) {
            if (false !== $db->query('SELECT 1 FROM mf_order_line WHERE ALLOW_DIGITAL_QUANTITY = 1 LIMIT 1')->fetch()) {
                throw new \RuntimeException('Заказы уже содержат снимок новой настройки количества. Откат схемы потеряет эти данные.');
            }
            $db->queryExecute('ALTER TABLE mf_order_line DROP CHECK ck_mf_order_line_values, DROP COLUMN ALLOW_DIGITAL_QUANTITY,'
                . ' ADD CONSTRAINT ck_mf_order_line_values CHECK (' . self::LINE_VALUES . " AND (PRODUCT_KIND = 'physical' OR QUANTITY = 1))");
        }
    }

    private function hasOrderFlag(): bool
    {
        return false !== Application::getConnection()->query("SHOW COLUMNS FROM mf_order_line WHERE Field = 'ALLOW_DIGITAL_QUANTITY'")->fetch();
    }
}
