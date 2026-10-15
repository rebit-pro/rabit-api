<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Morefoto\Production\Application\Archive\Contract\ArchiveLinkInterface;
use Morefoto\Production\Application\Archive\UseCase\OpenProductionArchiveUseCase;
use Morefoto\Production\Application\Archive\UseCase\RenewProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Rebit\Share\Shared\Exception\HttpException;

// L1: MySQL and disk trail of the print archive — the version holds exactly the paid print of the group with
// quantity × printCount, the archive on disk matches its size, only the hash of the current link is stored, a second run
// adds nothing, and an archive past its retention is deleted, refuses its link and is built again on renewal.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/l1-archive.json')) {
    throw new RuntimeException('Production verification requires the disposable test runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.production')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Production module.');
}
$connection = Application::getConnection();
$sql = $connection->getSqlHelper();
$record = json_decode((string)file_get_contents('/runtime/l1-archive.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('L1 verification failed: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$connection->query($query)->fetchRaw());
$root = rtrim((string)getenv('MOREFOTO_PRIVATE_FILES_PATH'), '/');
$check('' !== $root && is_dir($root), 'the private files root exists');
$check(ModuleManager::isModuleInstalled('morefoto.production'), 'the module is registered');
$versionId = $sql->forSql((string)$record['versionId']);
$groupId = $sql->forSql((string)$record['groupId']);
$version = $connection->query("SELECT * FROM mf_production_version WHERE PUBLIC_ID='{$versionId}' AND GROUP_PUBLIC_ID='{$groupId}'")->fetch();
$check(is_array($version), 'the version of the browser exists');
$id = (int)$version['ID'];

// 1. Exactly the paid print of the group before its deadline, each order line once, prints = quantity × printCount.
$paid = $scalar("SELECT COUNT(*) FROM mf_order o JOIN mf_order_line l ON l.ORDER_ID=o.ID WHERE o.GROUP_PUBLIC_ID='{$groupId}'
    AND o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=0 AND o.PAID_AT<'{$version['DEADLINE_AT']}' AND l.PRODUCT_KIND='physical'");
$check(0 < $paid && $paid === (int)$version['LINES_COUNT'], 'the version holds every paid print line of the group');
$check($paid === $scalar("SELECT COUNT(*) FROM mf_production_line p JOIN mf_order_line l ON l.PUBLIC_ID=p.ORDER_LINE_ID
    WHERE p.VERSION_ID={$id} AND l.PRODUCT_KIND='physical' AND p.QUANTITY=l.QUANTITY AND p.PRINT_COUNT=l.PRINT_COUNT AND p.PRINTS=l.QUANTITY*l.PRINT_COUNT
    AND p.PRODUCT_NAME=l.PRODUCT_NAME AND p.PHOTO_PUBLIC_ID=l.PHOTO_PUBLIC_ID"), 'a line differs from its paid order line');
$check((int)$version['PRINTS_COUNT'] === $scalar("SELECT SUM(PRINTS) FROM mf_production_line WHERE VERSION_ID={$id}"), 'the prints of the version');
$check((int)$version['FILES_COUNT'] === $scalar("SELECT COUNT(DISTINCT ENTRY) FROM mf_production_line WHERE VERSION_ID={$id}"), 'the files of the version');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_production_line p JOIN mf_order_line l ON l.PUBLIC_ID=p.ORDER_LINE_ID WHERE l.PRODUCT_KIND<>'physical'"), 'a digital line entered print');
$check(0 === $scalar('SELECT COUNT(*) FROM (SELECT ORDER_LINE_ID FROM mf_production_line GROUP BY GROUP_ID,DEADLINE_AT,ORDER_LINE_ID HAVING COUNT(*)>1) d'), 'a line entered two versions');

// 2. The archive on disk matches the row; only the hash of the current (renewed) link is stored.
$check('ready' === $version['STATUS'] && 2 === (int)$version['LINK_GENERATION'], 'the renewed version is ready');
$path = $root . '/' . $version['ARCHIVE_PATH'];
$check(is_file($path) && filesize($path) === (int)$version['BYTES'], 'the archive file matches its size');
$links = ServiceLocator::getInstance()->get(ArchiveLinkInterface::class);
$token = (string)$record['token'];
$check($links->hash($token) === $version['LINK_HASH'] && $links->token((string)$record['versionId'], 2) === $token, 'the stored hash is the current link');
$dump = '';
foreach (['mf_production_version', 'mf_production_line', 'mf_production_request', 'mf_production_schedule'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetchRaw())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
$check(!str_contains($dump, $token) && !str_contains($dump, (string)$record['revokedToken']), 'a raw link is stored');
$check(2 === $scalar("SELECT COUNT(*) FROM mf_production_request WHERE VERSION_ID={$id}"), 'the build and the renewal of the organizer are remembered');

// 3. A second run adds no version; an archive past its retention is deleted and refuses its link.
$services = ServiceLocator::getInstance();
$run = $services->get(RunProductionUseCase::class)->execute(20);
$check(1 === $scalar("SELECT COUNT(*) FROM mf_production_version WHERE GROUP_PUBLIC_ID='{$groupId}'"), 'a second run created a version of the group');
$connection->queryExecute("UPDATE mf_production_version SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE ID={$id}");
$check(1 <= $services->get(RunProductionUseCase::class)->execute(20)->expired, 'the expired archive was not removed');
$check(!is_file($path) && 'expired' === $connection->query("SELECT STATUS FROM mf_production_version WHERE ID={$id}")->fetch()['STATUS'], 'the archive is deleted');
try {
    $services->get(OpenProductionArchiveUseCase::class)->execute($token);
    $check(false, 'the expired link still opens');
} catch (HttpException $error) {
    $check('ARCHIVE_NOT_FOUND' === $error->getMessage() && 404 === $error->getCode(), 'the expired link answers 404');
}

// 4. Renewal of the deleted archive builds the same composition again with a new link.
$organizer = $scalar("SELECT ID FROM b_user WHERE EMAIL='organizer@example.invalid'");
$check(0 < $organizer, 'the fixture organizer exists');
$renewed = $services->get(RenewProductionVersionUseCase::class)->execute($organizer, (string)$record['versionId'], bin2hex(random_bytes(16)));
$check('pending' === $renewed->status, 'the deleted archive waits for a new build');
$check(1 <= $services->get(RunProductionUseCase::class)->execute(20)->built, 'the archive was not built again');
$rebuilt = $connection->query("SELECT * FROM mf_production_version WHERE ID={$id}")->fetch();
$check('ready' === $rebuilt['STATUS'] && 3 === (int)$rebuilt['LINK_GENERATION'] && is_file($path) && filesize($path) === (int)$rebuilt['BYTES'], 'the rebuilt archive is ready');
$check($paid === (int)$rebuilt['LINES_COUNT'], 'the rebuild kept the composition');
$check('/_protected/files/' . $version['ARCHIVE_PATH'] === $services->get(OpenProductionArchiveUseCase::class)->execute($links->token((string)$record['versionId'], 3))->internalUri, 'the new link opens the archive');
echo "L1 production integration passed\n";
