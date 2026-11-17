<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\DB\MysqliConnection;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Application\Files\Contract\ProtectedStorageInterface;
use Morefoto\Files\Application\Institution\Contract\DigitalArchiveWriterInterface;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveBuild;
use Morefoto\Files\Application\Institution\Service\DigitalArchiveView;
use Morefoto\Files\Application\Institution\UseCase\CreateInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\DownloadInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\MaintainInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Domain\Institution\Repository\DigitalArchiveRepositoryInterface;
use Morefoto\Files\Domain\Institution\ValueObject\DigitalArchive;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\PurchasedDigitalLinesInterface;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Sprint\Migration\Version20261117180001;

if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/institution-digital.json')) {
    throw new RuntimeException('Digital verification requires the disposable Bitrix/MySQL runtime and browser evidence.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load real Files providers.');
}
$checks = 0;
$check = static function(bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Institution digital verification: ' . $message);
    }
    ++$checks;
};
$db = Application::getConnection();
$text = static fn(string $value): string => "'" . $db->getSqlHelper()->forSql($value) . "'";
$services = ServiceLocator::getInstance();
$record = json_decode((string)file_get_contents('/runtime/institution-digital.json'), true, flags: JSON_THROW_ON_ERROR);
$fixture = json_decode((string)file_get_contents('/runtime/institution-digital-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$archives = $services->get(DigitalArchiveRepositoryInterface::class);
$archive = $archives->find($record['archiveId']) ?? throw new RuntimeException('Browser archive is missing.');
$check('ready' === $archive->status && 4 === count($archive->files), 'complete archive saved after real HTTP and worker');
$check(null !== $archive->readyAt && $archive->readyAt->modify('+7 days') == $archive->expiresAt, 'seven days begin at readiness');
$check('ORIGINAL_MISSING' === $archives->find($record['failedId'])?->error, 'missing original failed without partial readiness');
$storage = $services->get(ProtectedStorageInterface::class);
$writer = $services->get(DigitalArchiveWriterInterface::class);
$path = $storage->absoluteArchivePath((string)$archive->archivePath);
$check($record['sha256'] === hash_file('sha256', $path) && $record['bytes'] === filesize($path), 'browser bytes match private disk');
$check(1000 === fileowner($path) && 0600 === (fileperms($path) & 0777), 'worker owns a private downloadable ZIP');
$zip = new ZipArchive();
$check(true === $zip->open($path) && 5 === $zip->numFiles, 'four photos and the manifest, no gifts');
$manifest = $writer->manifest($archive);
$check($manifest === $zip->getFromName('manifest.csv'), 'saved manifest is the ZIP manifest');
$entryNames = [];
$actualPhotos = [];
$sourceIds = [];
$sourceFiles = [];
$originals = $services->get(OriginalFilesInterface::class)->originals(array_keys($fixture['expectedPhotos']));
foreach ($archive->files as $file) {
    $check('0001.JPG' === $file->filename && $file->filename === basename($file->entryName), 'original basename remains exact');
    $check(!isset($entryNames[$file->entryName]) && !str_contains($file->entryName, 'шт/'), 'unique group/photo paths without print quantities');
    $entryNames[$file->entryName] = true;
    $actualPhotos[$file->photoId] = true;
    $contents = $zip->getFromName($file->entryName);
    $check(is_string($contents) && $fixture['expectedPhotos'][$file->photoId] === hash('sha256', $contents), 'ZIP contains exact original bytes');
    foreach ($file->sources as $source) {
        $line = $db->query('SELECT o.PAYMENT_STATUS,o.LATE_PAYMENT,l.PRODUCT_KIND,l.COVERED_BY_GIFT FROM mf_order_line l INNER JOIN mf_order o ON o.ID=l.ORDER_ID WHERE l.PUBLIC_ID=' . $text($source['lineId']))->fetchRaw();
        $check(is_array($line) && 'paid' === $line['PAYMENT_STATUS'] && 0 === (int)$line['LATE_PAYMENT']
            && 0 === (int)$line['COVERED_BY_GIFT'] && in_array($line['PRODUCT_KIND'], ['digital', 'bundle'], true), 'each source is a paid purchased electronic line');
        $sourceIds[$source['lineId']] = true;
    }
    $original = $originals[$file->photoId];
    $sourceFiles[] = ['path' => $original->absolutePath, 'name' => $file->entryName, 'bytes' => $original->bytes];
}
$zip->close();
$expectedIds = array_keys($fixture['expectedPhotos']);
$actualIds = array_keys($actualPhotos);
sort($expectedIds);
sort($actualIds);
$check($expectedIds === $actualIds && [] === array_intersect_key($actualPhotos, $fixture['excludedPhotos']), 'only both bought child sets, no gift/physical/unpaid/late neighbours');
$eligible = $services->get(PurchasedDigitalLinesInterface::class)->forInstitution($archive->institutionId, $archive->capturedAt);
$lineIds = [];
foreach ($eligible as $line) {
    $lineIds[$line->lineId] = true;
}
ksort($sourceIds);
ksort($lineIds);
$check($sourceIds === $lineIds, 'every eligible purchase is represented and no other source enters the snapshot');
$oldKeyHash = hash('sha256', $fixture['oldKey']);
$keyBefore = $db->query('SELECT EXPIRES_AT FROM mf_order_access_key WHERE KEY_HASH=' . $text($oldKeyHash))->fetchRaw();
$check(is_array($keyBefore) && new DateTimeImmutable($keyBefore['EXPIRES_AT'], new DateTimeZone('UTC')) < new DateTimeImmutable('now', new DateTimeZone('UTC')), 'historical buyer key remains expired');
$check(!str_contains($manifest, $fixture['oldKey']), 'manifest has no buyer credential');
$summaries = $archives->latest($archive->institutionId);
$check(2 === count($summaries) && is_int($summaries[0]['files']) && !array_key_exists('snapshot', $summaries[0]), 'list reads metadata rather than decoding all compositions');

// Real concurrent MySQL sessions: one connection cannot take or release the other connection's named lock.
$config = Configuration::getValue('connections')['default'];
$other = new MysqliConnection($config);
$lock = $text('mf.digital.' . substr(hash('sha256', 'native-lock'), 0, 48));
$archives->exclusive('native-lock', static function() use ($other, $lock, $check): void {
    $check(0 === (int)$other->query("SELECT GET_LOCK({$lock},0) AS VALUE")->fetchRaw()['VALUE'], 'a second MySQL session cannot acquire the owner lock');
    $check(0 === (int)$other->query("SELECT RELEASE_LOCK({$lock}) AS VALUE")->fetchRaw()['VALUE'], 'a second MySQL session cannot release the owner lock');
});
$check(1 === (int)$other->query("SELECT GET_LOCK({$lock},0) AS VALUE")->fetchRaw()['VALUE'], 'owner release frees the scoped lock');
$other->query("SELECT RELEASE_LOCK({$lock})");
$other->disconnect();

// Crash after writing the first attempt but before readiness: a new owner rebuilds, the stale owner cannot publish.
$ids = $services->get(DownloadIdGeneratorInterface::class);
$clock = $services->get(ClockInterface::class);
$now = $clock->now();
$retryId = $ids->uuid();
$retry = new DigitalArchive(
    id: $retryId,
    institutionId: $archive->institutionId,
    institutionPublicId: $archive->institutionPublicId,
    actorId: $archive->actorId,
    keyHash: hash('sha256', $retryId),
    capturedAt: $now,
    files: $archive->files,
);
$archives->insert($retry);
$owner = $ids->uuid();
$workPath = 'institution-digital/' . $retry->institutionPublicId . '/' . $retryId . '/' . $owner . '.zip';
$check($archives->claim($retryId, $owner, $workPath, $now, $now->modify('+30 minutes')), 'first real CAS acquires one attempt');
$check(!$archives->claim($retryId, $ids->uuid(), $workPath, $now, $now->modify('+30 minutes')), 'a duplicate cannot claim an active lease');
$writer->build($sourceFiles, $writer->manifest($retry), $storage->absoluteArchivePath($workPath));
$partial = $storage->absoluteArchivePath($workPath) . '.0123456789abcdef.tmp';
file_put_contents($partial, 'interrupted ZIP');
$db->queryExecute('UPDATE mf_institution_digital_archive SET NEXT_AT=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE PUBLIC_ID=' . $text($retryId));
$services->get(DigitalArchiveBuild::class)->execute($retryId);
$recovered = $archives->find($retryId);
$check(null !== $recovered && 'ready' === $recovered->status && 2 === $recovered->attempts && 2 === count($recovered->workPaths), 'expired lease recovers with both attempt paths recorded');
$check(!$archives->ready($retryId, $owner, $workPath, 1, $now, $now->modify('+7 days')), 'stale owner cannot overwrite the recovered archive');

// Empty result is persisted without publication or a ZIP; rollback removes only this native probe.
$db->startTransaction();
try {
    $emptyInstitution = $ids->uuid();
    $db->queryExecute('INSERT INTO b_hlbd_mf_institution(UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('
        . $text($emptyInstitution) . ",'Пустая выгрузка E2E','',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $empty = $services->get(CreateInstitutionDigitalArchiveUseCase::class)->execute($archive->actorId, $emptyInstitution, str_replace('-', '', $ids->uuid()));
    $check('empty' === $empty->status && 0 === $empty->files && null === $archives->find($empty->id)?->archivePath, 'empty is distinct from ready and does not write a ZIP');
} finally {
    $db->rollbackTransaction();
}

Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20261117180001.php';
$countBefore = (int)$db->query('SELECT COUNT(*) AS TOTAL FROM mf_institution_digital_archive')->fetchRaw()['TOTAL'];
(new Version20261117180001())->up();
$check($countBefore === (int)$db->query('SELECT COUNT(*) AS TOTAL FROM mf_institution_digital_archive')->fetchRaw()['TOTAL'], 'migration replay preserves snapshots');
try {
    (new Version20261117180001())->down();
    throw new RuntimeException('Destructive down unexpectedly succeeded.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'откат запрещён'), 'down refuses to delete saved compositions');
}

// Expiration is enforced before cron. Purge removes every attempt file, preserving manifests, buyer dates and originals.
$download = $services->get(DownloadInstitutionDigitalArchiveUseCase::class);
$ticket = $download->link($archive->actorId, $archive->id);
$db->queryExecute('UPDATE mf_institution_digital_archive SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE PUBLIC_ID IN (' . $text($archive->id) . ',' . $text($retryId) . ')');
$check('expired' === $services->get(DigitalArchiveView::class)->describe($archives->find($archive->id))->status, 'deadline closes readiness before purge');
try {
    $download->open($ticket->ticket);
    throw new RuntimeException('Expired content unexpectedly opened.');
} catch (HttpException $error) {
    $check('ARCHIVE_NOT_FOUND' === $error->getMessage(), 'a signed ticket does not extend ZIP lifetime');
}
$purge = $services->get(MaintainInstitutionDigitalArchivesUseCase::class)->purge(100);
$check(0 === $purge->failed && 2 <= $purge->processed, 'real purge removes expired/failed attempts');
$check(!is_file($path) && !is_file($storage->absoluteArchivePath($workPath)) && !is_file($storage->absoluteArchivePath((string)$recovered->archivePath)), 'all recorded files removed');
$check(!is_file($partial), 'interrupted temporary ZIP is removed with its recorded attempt');
$expired = $archives->find($archive->id);
$check(null !== $expired && 'expired' === $expired->status && $manifest === $writer->manifest($expired), 'immutable composition and CSV survive cleanup');
foreach ($fixture['originalPaths'] as $originalPath) {
    $check(is_file($originalPath), 'cleanup keeps Media originals');
}
$keyAfter = $db->query('SELECT EXPIRES_AT FROM mf_order_access_key WHERE KEY_HASH=' . $text($oldKeyHash))->fetchRaw();
$check($keyBefore === $keyAfter, 'staff export never changes buyer expiration');

// 64 MiB stored without compression under a bounded process working set; no complete source buffer in PHP or libzip.
$large = '/runtime/digital-large-source.bin';
$largeZip = '/runtime/digital-large-output.zip';
$handle = fopen($large, 'w+b');
$check(false !== $handle && ftruncate($handle, 64 * 1024 * 1024), 'large source fixture created');
fclose($handle);
$beforePeak = memory_get_peak_usage(true);
$beforeRss = getrusage()['ru_maxrss'];
try {
    $writer->build([['path' => $large, 'name' => 'large/0001.JPG', 'bytes' => 64 * 1024 * 1024]], 'manifest', $largeZip);
    $peakDelta = memory_get_peak_usage(true) - $beforePeak;
    $rssDelta = getrusage()['ru_maxrss'] - $beforeRss;
    $check(16 * 1024 * 1024 > $peakDelta && 32 * 1024 > $rssDelta, 'large ZIP uses a bounded working set');
} finally {
    unlink($large);
    if (is_file($largeZip)) {
        unlink($largeZip);
    }
}
file_put_contents('/runtime/institution-digital-proof.json', json_encode(['checks' => $checks, 'files' => count($archive->files), 'purchasedLines' => count($sourceIds), 'largeSourceBytes' => 64 * 1024 * 1024,
    'phpPeakDelta' => $peakDelta, 'rssDeltaKiB' => $rssDelta, 'buyerExpiryPreserved' => true], JSON_THROW_ON_ERROR));
echo "Institution digital integration passed: {$checks} assertions; large source: 67108864 bytes; PHP peak delta: {$peakDelta} bytes; RSS delta: {$rssDelta} KiB\n";
