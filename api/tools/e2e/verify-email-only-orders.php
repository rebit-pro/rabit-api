<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Sprint\Migration\Version20261012100001;

if ('test' !== getenv('APP_ENV')) {
    throw new RuntimeException('Email-only order verification requires the disposable stand.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('sprint.migration');
$connection = Application::getConnection();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('E8 check failed: ' . $message);
    }
};
$scalar = static fn(string $sql): int => (int)current((array)$connection->query($sql)->fetch());
$rejected = static function(string $sql) use ($connection): bool {
    try {
        $connection->queryExecute($sql);
    } catch (Throwable) {
        return true;
    }

    return false;
};
$proof = [];

// 1. Every order of the browser scenarios keeps the email only, also the one sent by an older page with name and phone.
$check(0 < $scalar('SELECT COUNT(*) FROM mf_order'), 'the browser scenarios placed orders');
$check(0 === $scalar('SELECT COUNT(*) FROM mf_order WHERE BUYER_NAME IS NOT NULL OR BUYER_PHONE IS NOT NULL'), 'no order stores a name or a phone');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_order WHERE BUYER_EMAIL = ''"), 'every order has an email');
$nullable = $connection->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY COLUMN_NAME) AS C FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mf_order' AND COLUMN_NAME IN ('BUYER_NAME','BUYER_PHONE','BUYER_EMAIL') AND IS_NULLABLE='YES'")->fetch();
$check('BUYER_NAME,BUYER_PHONE' === (string)$nullable['C'], 'name and phone are nullable, the email is not');
$proof[] = 'email-only orders';

// 2. The CHECK keeps the E5 rule for a stored name or phone of an earlier order; nothing is committed.
$id = $scalar('SELECT ID FROM mf_order ORDER BY ID LIMIT 1');
$connection->startTransaction();
try {
    $check(!$rejected("UPDATE mf_order SET BUYER_NAME='Анна Тестовая', BUYER_PHONE='+79005550102' WHERE ID={$id}"), 'an earlier order with a name and a phone is valid');
    $check($rejected("UPDATE mf_order SET BUYER_NAME='А' WHERE ID={$id}"), 'a one-letter name is refused');
    $check($rejected("UPDATE mf_order SET BUYER_PHONE='12' WHERE ID={$id}"), 'a malformed phone is refused');
    $check($rejected("UPDATE mf_order SET BUYER_EMAIL='' WHERE ID={$id}"), 'an empty email is refused');
} finally {
    $connection->rollbackTransaction();
}
$check(0 === $scalar("SELECT COUNT(*) FROM mf_order WHERE ID={$id} AND BUYER_NAME IS NOT NULL"), 'the probe left no data');
$proof[] = 'CHECK for stored contacts';

// 3. Migration replay keeps the schema; the rollback refuses while orders have no name or phone.
require_once '/app/public/local/php_interface/migrations.foundation/Version20261012100001.php';
(new Version20261012100001())->up();
$check(0 === $scalar('SELECT COUNT(*) FROM mf_order WHERE BUYER_NAME IS NOT NULL'), 'replay changed no order');
try {
    (new Version20261012100001())->down();
    $check(false, 'down() must refuse while email-only orders exist');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'made-up values'), 'rollback refusal: ' . $error->getMessage());
}
$proof[] = 'migration replay and guarded rollback';

echo 'E8 email-only order integration passed: ' . implode(', ', $proof) . PHP_EOL;
