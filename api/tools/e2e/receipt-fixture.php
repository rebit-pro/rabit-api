<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Payment\Application\Payment\Contract\PaymentTransactionInterface;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Application\Receipt\Service\ReceiptReconciler;
use Morefoto\Payment\Domain\Payment\Repository\PaymentAttemptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Morefoto\Payment\Domain\Receipt\Service\ReceiptPolicy;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Clock\ClockInterface;

// G2: the stand has no cron. On a request of the browser it runs the receipt reconciliation of app:payment:reconcile for
// every open receipt at once (the schedule pauses are not waited for): receipts of fixture payments go to the fixture
// provider, receipts of the test shop payment go to the real YooKassa test shop. Payment receipts run before closing ones.
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/g2-request.json')) {
    throw new RuntimeException('The receipt fixture requires the disposable stand and a request of the browser.');
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
require __DIR__ . '/payment-fixture-provider.php';
$request = json_decode((string)file_get_contents('/runtime/g2-request.json'), true, flags: JSON_THROW_ON_ERROR);
if ('reconcile' !== ($request['action'] ?? '')) {
    throw new RuntimeException('Unknown receipt fixture action.');
}
$services = ServiceLocator::getInstance();
$fixture = new ReceiptReconciler(
    $services->get(PaymentReceiptRepositoryInterface::class),
    $services->get(PaymentAttemptRepositoryInterface::class),
    new E2eFixturePaymentProvider(),
    $services->get(PaymentTransactionInterface::class),
    $services->get(ReceiptSnapshotMapper::class),
    $services->get(ReceiptPolicy::class),
    $services->get(ClockInterface::class),
    new NullLogger(),
);
$connection = Application::getConnection();
foreach (['payment', 'closing'] as $kind) {
    $open = $connection->query("SELECT ID,PROVIDER FROM mf_payment_receipt WHERE KIND='{$kind}' AND STATUS IN ('queued','pending') ORDER BY ID");
    while ($row = $open->fetch()) {
        // The test shop answers for its own payment: the receipt goes through the configured provider of the stand.
        $reconciler = 'e2e-fixture' === $row['PROVIDER'] ? $fixture : $services->get(ReceiptReconciler::class);
        $reconciler->reconcile((int)$row['ID']);
    }
}
$receipts = [];
$result = $connection->query("SELECT PUBLIC_ID,KIND,PROVIDER,ORDER_PUBLIC_ID,STATUS,ERROR,FISCAL_DOCUMENT_NUMBER,PROVIDER_RECEIPT_ID FROM mf_payment_receipt ORDER BY ID");
while ($row = $result->fetch()) {
    $receipts[] = [
        'id' => $row['PUBLIC_ID'],
        'kind' => $row['KIND'],
        'provider' => $row['PROVIDER'],
        'orderId' => $row['ORDER_PUBLIC_ID'],
        'status' => $row['STATUS'],
        'error' => $row['ERROR'],
        'fiscalDocumentNumber' => $row['FISCAL_DOCUMENT_NUMBER'],
        'providerReceiptId' => $row['PROVIDER_RECEIPT_ID'],
    ];
}
$closing = is_file(E2eFixturePaymentProvider::CLOSING_LOG)
    ? json_decode((string)file_get_contents(E2eFixturePaymentProvider::CLOSING_LOG), true, flags: JSON_THROW_ON_ERROR)
    : [];
file_put_contents('/runtime/g2-answer.json', json_encode(['receipts' => $receipts, 'closing' => $closing], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
echo "G2 receipt fixture: answered\n";
