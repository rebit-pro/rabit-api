<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Sprint\Migration\Version20260925120001;

if ('test' !== getenv('APP_ENV') || !is_file('/runtime/e4-fixture.json')) {
    throw new RuntimeException('Payment cost verification requires the disposable fixture.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20260925120001.php';
$connection = Application::getConnection();
$assert = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$policy = static function() use ($connection): array {
    $row = $connection->query('SELECT PAYMENT_COSTS_ENABLED,PAYMENT_COST_RATE_BPS FROM mf_sales_conditions WHERE ID=1')->fetch();
    if (false === $row) {
        throw new RuntimeException('Global sales conditions are missing.');
    }

    return [(int)$row['PAYMENT_COSTS_ENABLED'], (int)$row['PAYMENT_COST_RATE_BPS']];
};
$constraints = [
    'mf_sales_conditions' => 'ck_mf_sales_conditions_payment_costs',
    'b_hlbd_mf_product' => 'ck_mf_product_price_cap',
    'mf_group_product_condition' => 'ck_mf_group_product_condition_price_cap',
];
$schema = static function() use ($connection, $constraints): bool {
    foreach ($constraints as $table => $name) {
        if (false === $connection->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND CONSTRAINT_NAME='{$name}'")->fetch()) {
            return false;
        }
    }

    return false !== $connection->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mf_sales_conditions' AND COLUMN_NAME='PAYMENT_COST_RATE_BPS'")->fetch();
};
// Every probe runs in a rolled back transaction: a missing CHECK must not leave a broken price behind.
$rejected = static function(string $sql) use ($connection): bool {
    $connection->startTransaction();
    try {
        $connection->queryExecute($sql);

        return false;
    } catch (Throwable) {
        return true;
    } finally {
        $connection->rollbackTransaction();
    }
};

$assert([0, 380] === $policy(), 'The browser must leave the payment cost policy off at 3.80 %.');
$assert($schema(), 'Policy columns and CHECK limits must be installed.');
$assert($rejected('UPDATE mf_sales_conditions SET PAYMENT_COST_RATE_BPS=1001 WHERE ID=1'), 'MySQL must reject a rate above 10 %.');
$assert($rejected('UPDATE mf_sales_conditions SET PAYMENT_COSTS_ENABLED=2 WHERE ID=1'), 'MySQL must reject a non-boolean policy flag.');
$assert($rejected('UPDATE b_hlbd_mf_product SET UF_PRICE=100000001 ORDER BY ID LIMIT 1'), 'MySQL must reject a catalogue price above 1 000 000 RUB.');
$assert(false !== $connection->query('SELECT 1 FROM mf_group_product_condition LIMIT 1')->fetch(), 'The storefront spec must leave group prices to probe.');
$assert($rejected('UPDATE mf_group_product_condition SET PRICE=100000001 LIMIT 1'), 'MySQL must reject a group price above 1 000 000 RUB.');

$migration = new Version20260925120001();
$migration->up();
$assert([0, 380] === $policy() && $schema(), 'Replaying the migration must not change the schema or the policy.');
$connection->queryExecute('UPDATE mf_sales_conditions SET PAYMENT_COSTS_ENABLED=1 WHERE ID=1');
try {
    $migration->down();
    $refused = false;
} catch (RuntimeException) {
    $refused = true;
} finally {
    $connection->queryExecute('UPDATE mf_sales_conditions SET PAYMENT_COSTS_ENABLED=0 WHERE ID=1');
}
$assert($refused && $schema(), 'Rollback must refuse to drop an enabled policy.');
$migration->down();
$assert(!$schema(), 'Rollback of a disabled policy must drop its columns and CHECK limits.');
$migration->up();
$assert([0, 380] === $policy() && $schema(), 'Reinstalling the migration must restore the defaults and limits.');

echo "E6 payment cost integration passed: policy left off, CHECK limits for rate and prices, migration replay and guarded rollback.\n";
