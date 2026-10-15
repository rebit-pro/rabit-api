<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;

// J2: MySQL trail of the letter with the link to the paid order — one automatic letter per order confirmed after the release,
// queued once in H1 under its deduplication key; the email key is a separate active key stored only as SHA-256, it lasts the
// files month and leaves the buyer key alone; a repeated dispatcher run adds nothing.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/j2-email.json')) {
    throw new RuntimeException('Order email verification requires the disposable test runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Files module.');
}
$connection = Application::getConnection();
$sql = $connection->getSqlHelper();
$record = json_decode((string)file_get_contents('/runtime/j2-email.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('J2 verification failed: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$connection->query($query)->fetchRaw());
$order = $connection->query("SELECT ID,DATE_FORMAT(PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,PAID_RECORDED_AT,BUYER_EMAIL FROM mf_order WHERE PUBLIC_ID='"
    . $sql->forSql((string)$record['orderId']) . "'")->fetchRaw();
$check(is_array($order) && null !== $order['PAID_RECORDED_AT'], 'the paid order has its confirmation moment');
$orderId = (int)$order['ID'];

// The cron of the stand is this call: orders paid after the browser's own run get their letters first.
ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);

// 1. One automatic letter for every paid order confirmed after the release, none for anything else.
$check(0 === $scalar("SELECT COUNT(*) FROM mf_order o LEFT JOIN mf_file_delivery d ON d.ORDER_ID=o.ID AND d.KIND='auto'
    WHERE o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=0 AND o.PAID_RECORDED_AT IS NOT NULL AND d.ID IS NULL"), 'a paid order has no letter');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_file_delivery d JOIN mf_order o ON o.ID=d.ORDER_ID WHERE d.KIND='auto'
    AND (o.PAYMENT_STATUS<>'paid' OR o.LATE_PAYMENT=1 OR o.PAID_RECORDED_AT IS NULL)"), 'a letter for an order that is not paid in time');
$check(0 === $scalar("SELECT COUNT(*) FROM (SELECT ORDER_ID FROM mf_file_delivery WHERE KIND='auto' GROUP BY ORDER_ID HAVING COUNT(*)>1) d"), 'two automatic letters');

// 2. The letter of the order is queued once in H1 under its own deduplication key and goes to the order email.
$letter = $connection->query("SELECT d.PUBLIC_ID,d.RECIPIENT,d.OPERATION_ID,n.CONSUMER_KEY,n.DEDUP_KEY,n.RECIPIENT AS TO_ADDRESS,n.MAX_ATTEMPTS
    FROM mf_file_delivery d JOIN b_rebit_notification_operation n ON n.ID=d.OPERATION_ID WHERE d.ORDER_ID={$orderId} AND d.KIND='auto' AND d.STATUS='queued'")->fetchRaw();
$check(is_array($letter) && 'files-order-email' === $letter['CONSUMER_KEY'] && 'order-email:' . $letter['PUBLIC_ID'] === $letter['DEDUP_KEY'], 'the queued letter');
$check($order['BUYER_EMAIL'] === $letter['RECIPIENT'] && $letter['RECIPIENT'] === $letter['TO_ADDRESS'] && 10 === (int)$letter['MAX_ATTEMPTS'], 'the letter goes to the order email');

// 3. The email key is a separate active key, stored only as SHA-256 and lasting the files month; the buyer key is intact.
$key = (string)$record['emailKey'];
$until = new OrderCalendarPolicy()->filesAvailableUntil(new DateTimeImmutable((string)$order['PAID_AT'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$check(1 === $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE ORDER_ID={$orderId} AND KIND='email' AND REVOKED_AT IS NULL AND KEY_HASH='"
    . hash('sha256', $key) . "' AND EXPIRES_AT='{$until}'"), 'the email key and its files month');
$check(1 === $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE ORDER_ID={$orderId} AND KIND='buyer' AND REVOKED_AT IS NULL AND KEY_HASH='"
    . hash('sha256', (string)$record['buyerKey']) . "'"), 'the buyer key stays active');
$dump = '';
foreach (['mf_file_delivery', 'mf_order_access_key', 'mf_order'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetchRaw())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
$check(!str_contains($dump, $key), 'a raw email key is stored');

// 4. A repeated run adds no letter and keeps one H1 operation per letter.
$before = $scalar('SELECT COUNT(*) FROM mf_file_delivery');
ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);
$check($before === $scalar('SELECT COUNT(*) FROM mf_file_delivery'), 'a repeated run added a letter');
$check(0 === $scalar("SELECT COUNT(*) FROM (SELECT DEDUP_KEY FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' GROUP BY DEDUP_KEY HAVING COUNT(*)>1) d"), 'a letter queued twice');
echo "J2 order email integration passed\n";
