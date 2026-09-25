<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Sprint\Migration\Version20260925120001;

// G1: MySQL trail of the payment scenarios — attempts, money facts and order statuses agree, no buyer secret is stored.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/g1-payments.json')) {
    throw new RuntimeException('Payment verification requires the disposable test runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
$connection = Application::getConnection();
$record = json_decode((string)file_get_contents('/runtime/g1-payments.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('G1 verification failed: ' . $message);
    }
};
$scalar = static fn(string $sql): int => (int)current((array)$connection->query($sql)->fetchRaw());

// 1. No buyer secret reaches the payment tables: keys are hashed, the provider never sees the order key.
$dump = '';
foreach (['mf_payment_attempt', 'mf_payment_fact', 'mf_payment_notification'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetchRaw())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
foreach ([...$record['accessKeys'], ...$record['idempotencyKeys']] as $secret) {
    $check(!str_contains($dump, (string)$secret), 'a raw order or idempotency key is stored');
}
$check(!str_contains($dump, (string)getenv('MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY')) || '' === (string)getenv('MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY'), 'the shop secret is stored');

// 2. Attempts, facts and orders agree.
$check(0 === $scalar('SELECT COUNT(*) FROM (SELECT ORDER_ID FROM mf_payment_attempt WHERE STATUS IN (\'unknown\',\'pending\') GROUP BY ORDER_ID HAVING COUNT(*) > 1) open'), 'an order has two open attempts');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_payment_attempt a LEFT JOIN mf_payment_fact f ON f.ATTEMPT_ID=a.ID WHERE (a.STATUS='succeeded') <> (f.ID IS NOT NULL)"), 'every succeeded attempt has exactly one money fact');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_payment_fact f JOIN mf_order o ON o.ID=f.ORDER_ID WHERE o.PAYMENT_STATUS<>'paid' OR o.PAID_AT IS NULL OR o.LATE_PAYMENT<>f.LATE_PAYMENT"), 'a paid fact left its order unpaid');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_order o WHERE o.PAYMENT_STATUS='paid' AND NOT EXISTS (SELECT 1 FROM mf_payment_fact f WHERE f.ORDER_ID=o.ID)"), 'an order is paid without a money fact');
$check(0 === $scalar('SELECT COUNT(*) FROM mf_payment_fact f JOIN mf_payment_attempt a ON a.ID=f.ATTEMPT_ID WHERE f.AMOUNT<>a.AMOUNT OR f.PROVIDER_PAYMENT_ID<>a.PROVIDER_PAYMENT_ID'), 'a fact differs from its attempt');
$check(0 === $scalar('SELECT COUNT(*) FROM mf_payment_attempt a JOIN mf_order o ON o.ID=a.ORDER_ID WHERE a.AMOUNT<>o.TOTAL OR a.ORDER_NUMBER<>o.NUMBER'), 'an attempt amount differs from the order snapshot');

if ($record['sandbox']) {
    $paid = $connection->getSqlHelper()->forSql((string)$record['paidOrderId']);
    $attempts = $connection->query("SELECT a.STATUS,a.PAYMENT_METHOD,a.CANCEL_REASON,a.PRECEDING_ID,a.ID FROM mf_payment_attempt a JOIN mf_order o ON o.ID=a.ORDER_ID
        WHERE o.PUBLIC_ID='{$paid}' ORDER BY a.ID")->fetchAll();
    $check(2 === count($attempts), 'the paid order has the refused SBP attempt and the card attempt');
    $check('canceled' === $attempts[0]['STATUS'] && 'sbp' === $attempts[0]['PAYMENT_METHOD'] && 'provider_rejected' === $attempts[0]['CANCEL_REASON'], 'the test shop refused SBP');
    $check('succeeded' === $attempts[1]['STATUS'] && 'bank_card' === $attempts[1]['PAYMENT_METHOD'] && (int)$attempts[0]['ID'] === (int)$attempts[1]['PRECEDING_ID'], 'the card attempt follows the refused one');
    $check(1 === $scalar("SELECT COUNT(*) FROM mf_payment_notification WHERE PROVIDER='yookassa' AND EVENT_KEY='payment.succeeded:" . $connection->getSqlHelper()->forSql((string)$record['providerPaymentId']) . "' AND RESULT='succeeded'"), 'the notification replay is recorded once');
} else {
    $check(0 === $scalar('SELECT COUNT(*) FROM mf_payment_attempt'), 'a disabled shop created an attempt');
}

// 3. Rollback never drops money silently.
Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20260925120001.php';
if ($record['sandbox']) {
    try {
        (new Version20260925120001())->down();
        throw new LogicException('down() must refuse while payment data exists.');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(), 'Payment data exists'), 'down() refuses with payment data');
    }
}

echo "G1 payment integration passed\n";
