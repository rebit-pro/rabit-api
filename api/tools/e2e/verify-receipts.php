<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Sprint\Migration\Version20261017180001;

// G2: MySQL trail of the receipts — every paid attempt that fixed a receipt has exactly one payment receipt for its whole
// amount; a closing receipt exists only for an order whose print was handed over, once, with the print positions of the
// payment receipt in full payment; nothing but the order email of the snapshot is stored, the schema refuses a closing
// receipt without its handover, and rollback refuses to drop fiscal documents.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/g2-receipts.json')) {
    throw new RuntimeException('Receipt verification requires the disposable test runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.payment')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Payment module.');
}
$connection = Application::getConnection();
$sql = $connection->getSqlHelper();
$record = json_decode((string)file_get_contents('/runtime/g2-receipts.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('G2 verification failed: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$connection->query($query)->fetchRaw());
/** @return list<array{description: string, quantity: int, amount: int, print: bool, mode: string}> */
$items = static fn(string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR);
$total = static fn(array $positions): int => array_sum(array_map(static fn(array $item): int => $item['quantity'] * $item['amount'], $positions));

// 1. Payment receipts: one per succeeded attempt with a snapshot, for the whole amount, none without a snapshot.
$check(0 === $scalar("SELECT COUNT(*) FROM mf_payment_attempt a LEFT JOIN mf_payment_receipt r ON r.ATTEMPT_ID=a.ID AND r.KIND='payment'
    WHERE a.STATUS='succeeded' AND a.RECEIPT IS NOT NULL AND r.ID IS NULL"), 'a paid attempt with a receipt has no payment receipt');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_payment_receipt r JOIN mf_payment_attempt a ON a.ID=r.ATTEMPT_ID WHERE a.RECEIPT IS NULL"), 'a receipt of an attempt without a snapshot');
$payments = $connection->query("SELECT r.ATTEMPT_ID,r.AMOUNT,r.ITEMS,r.PROVIDER_PAYMENT_ID,a.AMOUNT AS PAID,a.RECEIPT,a.PROVIDER_PAYMENT_ID AS ATTEMPT_PAYMENT
    FROM mf_payment_receipt r JOIN mf_payment_attempt a ON a.ID=r.ATTEMPT_ID WHERE r.KIND='payment'");
$paymentItems = [];
while ($row = $payments->fetchRaw()) {
    $positions = $items((string)$row['ITEMS']);
    $snapshot = json_decode((string)$row['RECEIPT'], true, flags: JSON_THROW_ON_ERROR);
    $check((int)$row['AMOUNT'] === (int)$row['PAID'] && $total($positions) === (int)$row['PAID'], 'the payment receipt covers exactly the paid amount');
    $check($row['PROVIDER_PAYMENT_ID'] === $row['ATTEMPT_PAYMENT'], 'the payment receipt belongs to the payment of its attempt');
    $check($positions === $snapshot['items'], 'the payment receipt repeats the snapshot sent with the payment');
    foreach ($positions as $item) {
        $check(($item['print'] ? 'full_prepayment' : 'full_payment') === $item['mode'], 'print is prepaid, digital is paid in full');
        $check(0 < $item['amount'] && 0 < $item['quantity'] && 1 <= mb_strlen($item['description']) && 128 >= mb_strlen($item['description']), 'a position breaks the provider limits');
    }
    $paymentItems[(int)$row['ATTEMPT_ID']] = $positions;
}

// 2. Closing receipts: once per handed order, the print of the payment receipt in full payment, with the handover.
$handed = array_map(static fn(string $id): string => "'" . $sql->forSql($id) . "'", [...$record['handedOrderIds'], ...('' !== $record['sandbox']['closing'] ? [$record['sandbox']['orderId']] : [])]);
$closings = $connection->query("SELECT ATTEMPT_ID,ORDER_PUBLIC_ID,AMOUNT,ITEMS,HANDED_AT,HANDED_BY,STATUS FROM mf_payment_receipt WHERE KIND='closing'");
$closed = [];
while ($row = $closings->fetchRaw()) {
    $closed[] = "'" . $row['ORDER_PUBLIC_ID'] . "'";
    $print = array_values(array_filter($paymentItems[(int)$row['ATTEMPT_ID']] ?? [], static fn(array $item): bool => $item['print']));
    $positions = $items((string)$row['ITEMS']);
    $check([] !== $print && array_map(static fn(array $item): array => [...$item, 'mode' => 'full_payment'], $print) === $positions, 'the closing receipt repeats the print of the payment receipt in full payment');
    $check($total($positions) === (int)$row['AMOUNT'] && null !== $row['HANDED_AT'] && 0 < (int)$row['HANDED_BY'], 'the closing receipt carries its amount and handover');
}
sort($handed);
sort($closed);
$check($handed === $closed, 'closing receipts exist exactly for the handed orders');
$j4 = $sql->forSql((string)$record['orderId']);
$check(2 === $scalar("SELECT COUNT(*) FROM mf_payment_receipt WHERE ORDER_PUBLIC_ID='{$j4}' AND STATUS='succeeded' AND FISCAL_DOCUMENT_NUMBER IS NOT NULL"), 'the J4 order has its registered payment and closing receipts');

// 3. The schema keeps a closing receipt tied to its handover and one receipt of a kind per attempt.
$row = $connection->query("SELECT * FROM mf_payment_receipt WHERE ORDER_PUBLIC_ID='{$j4}' AND KIND='closing'")->fetchRaw();
foreach ([
    'a closing receipt without its handover' => "INSERT INTO mf_payment_receipt(PUBLIC_ID,KIND,ATTEMPT_ID,ORDER_ID,ORDER_PUBLIC_ID,PROVIDER,PROVIDER_PAYMENT_ID,PROVIDER_KEY,AMOUNT,ITEMS,STATUS,CREATED_AT,UPDATED_AT)
        VALUES(UUID(),'closing',{$row['ATTEMPT_ID']},{$row['ORDER_ID']},'{$j4}','e2e-fixture','x',UUID(),100,'[]','queued',UTC_TIMESTAMP(),UTC_TIMESTAMP())",
    'a second closing receipt' => "INSERT INTO mf_payment_receipt(PUBLIC_ID,KIND,ATTEMPT_ID,ORDER_ID,ORDER_PUBLIC_ID,PROVIDER,PROVIDER_PAYMENT_ID,PROVIDER_KEY,AMOUNT,ITEMS,STATUS,HANDED_AT,CREATED_AT,UPDATED_AT)
        VALUES(UUID(),'closing',{$row['ATTEMPT_ID']},{$row['ORDER_ID']},'{$j4}','e2e-fixture','x',UUID(),100,'[]','queued',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())",
] as $message => $insert) {
    try {
        $connection->queryExecute($insert);
        throw new LogicException('The schema accepted ' . $message . '.');
    } catch (Bitrix\Main\DB\SqlQueryException) {
    }
}

// 4. Only the snapshot of the attempt keeps the order email: receipt rows hold neither an email nor the shop secret.
$dump = '';
$result = $connection->query('SELECT * FROM mf_payment_receipt');
while (false !== ($stored = $result->fetchRaw())) {
    $dump .= json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}
$check(!str_contains($dump, '@'), 'a receipt row stores an email');
$secret = (string)getenv('MOREFOTO_PAYMENT_YOOKASSA_SECRET_KEY');
$check('' === $secret || !str_contains($dump, $secret), 'the shop secret is stored');

// 5. Rollback never drops fiscal documents silently.
Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20261017180001.php';
try {
    (new Version20261017180001())->down();
    throw new LogicException('down() must refuse while receipts exist.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'Receipt data exists'), 'down() refuses with receipts');
}

echo "G2 receipts integration passed\n";
