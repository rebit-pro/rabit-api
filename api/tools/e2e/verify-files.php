<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Files\Application\Files\UseCase\PurgeDownloadsUseCase;
use Sprint\Migration\Version20260926180001;

// J1: MySQL and disk trail of the files scenarios — downloads agree with archives on disk, the paid order's key lasts
// until the end of the files month, no buyer secret is stored, the migration replays and expired archives are purged.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/j1-files.json')) {
    throw new RuntimeException('Files verification requires the disposable test runtime and browser results.');
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
$record = json_decode((string)file_get_contents('/runtime/j1-files.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('J1 verification failed: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$connection->query($query)->fetchRaw());
$root = rtrim((string)getenv('MOREFOTO_PRIVATE_FILES_PATH'), '/');
$check('' !== $root && is_dir($root), 'the private files root exists');
$check(ModuleManager::isModuleInstalled('morefoto.files'), 'the module is registered');

// 1. Nothing is stuck: no archive lock without a pending build, no build left pending, no secret in the journal.
$check(0 === $scalar("SELECT COUNT(*) FROM mf_file_download WHERE ACTIVE_ORDER_ID IS NOT NULL AND STATUS<>'pending'"), 'an archive lock outlived its build');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_file_download WHERE STATUS='pending'"), 'an archive build stayed pending');
$dump = '';
foreach (['mf_file_download', 'mf_file_download_request'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetchRaw())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
// Review #143 (2): every accepted key points at a download of its own order; the ZIP rows keep their path until purge.
$check(0 === $scalar('SELECT COUNT(*) FROM mf_file_download_request r JOIN mf_file_download d ON d.ID=r.DOWNLOAD_ID WHERE d.ORDER_ID<>r.ORDER_ID'), 'a key points at a foreign download');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_file_download WHERE KIND='zip' AND STATUS<>'expired' AND ARCHIVE_PATH IS NULL"), 'a live archive row lost its path');
$check('' === $record['accessKey'] || !str_contains($dump, (string)$record['accessKey']), 'a raw order key is stored');

if ($record['sandbox']) {
    $order = $connection->query("SELECT ID,DATE_FORMAT(PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT FROM mf_order WHERE PUBLIC_ID='" . $sql->forSql((string)$record['orderId']) . "'")->fetch();
    $check(is_array($order) && null !== $order['PAID_AT'], 'the files order is paid');
    $orderId = (int)$order['ID'];

    // 2. D07/D10: the key of the paid order lasts at least until the end of the files month.
    $until = new OrderCalendarPolicy()->filesAvailableUntil(new DateTimeImmutable((string)$order['PAID_AT'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $check(1 === $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE ORDER_ID={$orderId} AND REVOKED_AT IS NULL AND EXPIRES_AT>='{$until}'"), 'the paid order key was not extended');

    // 3. The browser downloads agree with the journal and the disk.
    $download = static fn(string $id): array|false => $connection->query("SELECT KIND,STATUS,ARCHIVE_PATH,BYTES,ORDER_ID FROM mf_file_download WHERE PUBLIC_ID='" . $sql->forSql($id) . "'")->fetch();
    $file = $download((string)$record['fileDownloadId']);
    $check(is_array($file) && 'file' === $file['KIND'] && 'ready' === $file['STATUS'] && null === $file['ARCHIVE_PATH'] && $orderId === (int)$file['ORDER_ID'], 'the single file download');
    $archive = $download((string)$record['archiveDownloadId']);
    $check(is_array($archive) && 'zip' === $archive['KIND'] && 'ready' === $archive['STATUS'] && $orderId === (int)$archive['ORDER_ID'], 'the archive download');
    $path = $root . '/' . $archive['ARCHIVE_PATH'];
    $check(is_file($path) && filesize($path) === (int)$archive['BYTES'] && (int)$record['archiveBytes'] === (int)$archive['BYTES'], 'the archive on disk matches the journal and the browser');
    $check(1000 === fileowner($path) && 0600 === (fileperms($path) & 0777), 'the archive is private and readable by the web server user');
    $check(1 === $scalar("SELECT COUNT(*) FROM mf_file_download WHERE ORDER_ID={$orderId} AND KIND='zip'"), 'the same composition reused one archive');
    $check(2 <= $scalar("SELECT COUNT(*) FROM mf_file_download_request r JOIN mf_file_download d ON d.ID=r.DOWNLOAD_ID WHERE d.PUBLIC_ID='" . $sql->forSql((string)$record['archiveDownloadId']) . "'"), 'the browser and the API keys are both bound to the reused archive');

    // 4. Expired archives leave the disk.
    $connection->queryExecute("UPDATE mf_file_download SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE PUBLIC_ID='" . $sql->forSql((string)$record['archiveDownloadId']) . "'");
    ServiceLocator::getInstance()->get(PurgeDownloadsUseCase::class)->execute(100);
    $expired = $download((string)$record['archiveDownloadId']);
    $check(!is_file($path) && is_array($expired) && 'expired' === $expired['STATUS'] && null === $expired['ARCHIVE_PATH'], 'purge removed the expired archive');
} else {
    $check(0 === $scalar('SELECT COUNT(*) FROM mf_file_download'), 'an unpaid order created a download');
}

// 5. The migration replays without touching the journal.
Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20260926180001.php';
$before = [$scalar('SELECT COUNT(*) FROM mf_file_download'), $scalar('SELECT COUNT(*) FROM mf_file_download_request')];
(new Version20260926180001())->up();
$check($before === [$scalar('SELECT COUNT(*) FROM mf_file_download'), $scalar('SELECT COUNT(*) FROM mf_file_download_request')], 'the migration replay changed downloads');

echo "J1 files integration passed\n";
