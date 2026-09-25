<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260925120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'E6: payment cost policy in global sales conditions and the 1 000 000 RUB price cap';

    private const int MAX_PRICE = 100000000;

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['mf_sales_conditions', 'mf_group_product_condition', 'b_hlbd_mf_product'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('E6 payment cost pricing requires the merged E3 schema.');
            }
        }
        $row = $connection->query('SELECT (SELECT COUNT(*) FROM b_hlbd_mf_product WHERE UF_PRICE>' . self::MAX_PRICE
            . ')+(SELECT COUNT(*) FROM mf_group_product_condition WHERE PRICE>' . self::MAX_PRICE . ') AS OVER_CAP')->fetch();
        if (false === $row || 0 !== (int)$row['OVER_CAP']) {
            throw new \RuntimeException('Catalogue or group prices exceed 1 000 000 RUB; lower them before applying E6.');
        }
        if (!$this->columnExists('mf_sales_conditions', 'PAYMENT_COSTS_ENABLED')) {
            $connection->queryExecute('ALTER TABLE mf_sales_conditions'
                . ' ADD COLUMN PAYMENT_COSTS_ENABLED TINYINT NOT NULL DEFAULT 0,'
                . ' ADD COLUMN PAYMENT_COST_RATE_BPS SMALLINT NOT NULL DEFAULT 380,'
                . ' ADD CONSTRAINT ck_mf_sales_conditions_payment_costs CHECK (PAYMENT_COSTS_ENABLED IN (0,1) AND PAYMENT_COST_RATE_BPS BETWEEN 0 AND 1000)');
        }
        $caps = ['b_hlbd_mf_product' => ['ck_mf_product_price_cap', 'UF_PRICE'], 'mf_group_product_condition' => ['ck_mf_group_product_condition_price_cap', 'PRICE']];
        foreach ($caps as $table => [$name, $column]) {
            if (!$this->constraintExists($table, $name)) {
                $connection->queryExecute('ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $name . ' CHECK (' . $column . '<=' . self::MAX_PRICE . ')');
            }
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if ($this->columnExists('mf_sales_conditions', 'PAYMENT_COSTS_ENABLED')) {
            $row = $connection->query('SELECT PAYMENT_COSTS_ENABLED FROM mf_sales_conditions WHERE ID=1')->fetch();
            if (false !== $row && 0 !== (int)$row['PAYMENT_COSTS_ENABLED']) {
                throw new \RuntimeException('The payment cost policy is enabled; disable it before rolling back E6.');
            }
            $connection->queryExecute('ALTER TABLE mf_sales_conditions DROP CHECK ck_mf_sales_conditions_payment_costs,'
                . ' DROP COLUMN PAYMENT_COST_RATE_BPS, DROP COLUMN PAYMENT_COSTS_ENABLED');
        }
        foreach (['b_hlbd_mf_product' => 'ck_mf_product_price_cap', 'mf_group_product_condition' => 'ck_mf_group_product_condition_price_cap'] as $table => $name) {
            if ($this->constraintExists($table, $name)) {
                $connection->queryExecute('ALTER TABLE ' . $table . ' DROP CHECK ' . $name);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return false !== Application::getConnection()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetch();
    }

    private function constraintExists(string $table, string $name): bool
    {
        return false !== Application::getConnection()->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND CONSTRAINT_NAME='{$name}'")->fetch();
    }
}
