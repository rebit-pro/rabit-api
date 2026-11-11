<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Contract\ProductionIdGeneratorInterface;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveBuilder;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\CreateInstitutionSupplementUseCase;
use Morefoto\Production\Domain\Archive\Exception\ProductionStorageException;
use Morefoto\Production\Application\Institution\UseCase\ExportInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\RenewInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\RunInstitutionProductionUseCase;
use Morefoto\Production\Domain\Archive\Enum\VersionStatusEnum;
use Morefoto\Production\Domain\Institution\Exception\InstitutionArchiveBusyException;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Media\PhotoFilenamesInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Sprint\Migration\Version20261110180001;

if ('test' !== getenv('APP_ENV') || !is_file('/runtime/institution-production.json') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Institution verification requires the isolated runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('morefoto.production');
$services = ServiceLocator::getInstance();
$archives = $services->get(InstitutionArchiveRepositoryInterface::class);
$runner = $services->get(RunInstitutionProductionUseCase::class);
if ('--probe-lock' === ($argv[1] ?? '')) {
    try {
        $archives->exclusive((string)$argv[2], static function(): void { echo 'ACQUIRED'; });
    } catch (InstitutionArchiveBusyException) {
        echo 'BUSY';
    }
    exit(0);
}
if ('--run' === ($argv[1] ?? '')) {
    $runner->execute(200);
    echo 'RUN-PASS';
    exit(0);
}
$db = Application::getConnection();
$sql = $db->getSqlHelper();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Institution print verification: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$db->query($query)->fetchRaw());
$record = json_decode((string)file_get_contents('/runtime/institution-production.json'), true, flags: JSON_THROW_ON_ERROR);
$fixture = json_decode((string)file_get_contents('/runtime/institution-print-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$institutionNative = (int)$fixture['institutionNativeId'];
$organizer = $scalar("SELECT ID FROM b_user WHERE EMAIL='organizer@example.invalid'");
$storage = $services->get(ArchiveStorageInterface::class);
$snapshot = $services->get(InstitutionSnapshot::class);
$export = $services->get(ExportInstitutionArchiveUseCase::class);
$renew = $services->get(RenewInstitutionArchiveUseCase::class);
$download = $services->get(DownloadInstitutionArchiveUseCase::class);

// The byte-level check follows the persisted line references, independently compares paid Commerce rows and Media.
$verify = static function(InstitutionArchive $archive) use ($snapshot, $storage, $export, $organizer, $services, $check): void {
    $check(VersionStatusEnum::READY === $archive->status && null !== $archive->archivePath, 'the release is ready');
    $lines = $snapshot->lines($archive);
    $check(count($lines) === count(array_unique(array_column($lines, 'orderLineId'))), 'a source line is repeated');
    $photoIds = array_values(array_unique(array_column($lines, 'photoId')));
    $originals = $services->get(OriginalFilesInterface::class)->originals($photoIds);
    $names = $services->get(PhotoFilenamesInterface::class)->filenames($photoIds);
    $zip = new ZipArchive();
    $check(true === $zip->open($storage->absolutePath((string)$archive->archivePath)), 'the real ZIP opens');
    $csv = $export->execute($organizer, $archive->publicId)->content;
    $check($csv === $zip->getFromName('состав.csv'), 'the HTTP export source equals the ZIP manifest');
    $check(str_starts_with($csv, "\xEF\xBB\xBF"), 'the CSV has a UTF-8 BOM');
    $rows = explode("\r\n", rtrim(substr($csv, 3), "\r\n"));
    $header = str_getcsv(array_shift($rows), ';', '"', '');
    $check(16 === count($header) && 'ID строки заказа' === $header[11] && 'Отпечатков в файле' === $header[15], 'layout v2 CSV columns stay exact');
    $check(count($rows) === $archive->lines, 'every source line is represented in CSV');
    $byLine = [];
    foreach ($rows as $row) {
        $cells = str_getcsv($row, ';', '"', '');
        $byLine[$cells[11]] = $cells;
    }
    $entries = [];
    $prints = 0;
    foreach ($lines as $line) {
        $cells = $byLine[$line->orderLineId] ?? [];
        $check(($cells[2] ?? null) === $line->entry && ($cells[12] ?? null) === $line->orderId && ($cells[13] ?? null) === $line->photoId, 'CSV keeps literal path and trace IDs');
        $check(basename($line->entry) === $names[$line->photoId], 'the original basename is preserved');
        $check(str_starts_with($line->entry, 'Архив учреждения E2E/') && !str_contains($line->entry, 'съёмка архива/') && !str_contains($line->entry, '_x'), 'institution/group/product layout without a shoot or quantity suffix');
        $entries[$line->entry] = true;
        $stream = $zip->getStream($line->entry);
        $check(false !== $stream, 'every manifest entry exists');
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);
        $check(hash_final($hash) === hash_file('sha256', $originals[$line->photoId]->absolutePath), 'original bytes match');
        $prints += $line->quantity * $line->printCount;
    }
    $check($zip->numFiles === count($entries) + 1 && count($entries) === $archive->files && $prints === $archive->prints, 'there are no extra or missing print files');
    $zip->close();
};
$history = $archives->history($institutionNative);
$check(2 === count($history), 'the browser produced exactly two cumulative releases');
$first = $archives->find((string)$record['firstId']);
$latest = $archives->find((string)$record['latestId']);
$check(null !== $first && null !== $latest, 'browser releases exist');
$verify($first);
$verify($latest);
$eligible = $scalar("SELECT COALESCE(SUM(l.QUANTITY*l.PRINT_COUNT),0) FROM mf_order o JOIN mf_order_line l ON l.ORDER_ID=o.ID
    JOIN b_hlbd_mf_group g ON g.ID=o.GROUP_ID WHERE o.INSTITUTION_ID={$institutionNative} AND o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=0
    AND o.PAID_AT<g.UF_CLOSES_AT AND l.PRODUCT_KIND='physical'");
$check($eligible === $latest->prints && $first->prints * 3 === $latest->prints * 2, 'the current full archive includes old print plus the new shoot');
$beforeHash = hash_file('sha256', $storage->absolutePath((string)$first->archivePath));
$beforeRows = $scalar("SELECT COUNT(*) FROM mf_production_line WHERE GROUP_ID IN (SELECT g.ID FROM b_hlbd_mf_group g JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE s.UF_INSTITUTION_ID={$institutionNative})");
$runner->execute(200);
$check(2 === count($archives->history($institutionNative)) && $beforeHash === hash_file('sha256', $storage->absolutePath((string)$first->archivePath)), 'repeat creates no new release and leaves old ZIP unchanged');

// Real MySQL locks across PHP processes, then two competing cron runs on one new closed shoot.
$start = static function(array $arguments): array {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start the second PHP connection.');
    }

    return [$process, $pipes];
};
$finish = static function(array $child) use ($check): string {
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $check(0 === proc_close($process) && '' === $error, 'the concurrent worker succeeded: ' . $error);

    return $out;
};
$lockKey = 'institution:' . (string)$fixture['institutionId'];
$archives->exclusive($lockKey, static function() use ($start, $finish, $check, $lockKey): void {
    $check('BUSY' === $finish($start(['--probe-lock', $lockKey])), 'another connection cannot enter or release the held mutex');
});
$check('ACQUIRED' === $finish($start(['--probe-lock', $lockKey])), 'the mutex is released after the operation');
require_once __DIR__ . '/institution-production-fixture.php';
institutionProductionFixture('institution-add-open', (string)$fixture['sourceGroup']);
institutionProductionFixture('institution-close-new', (string)$fixture['sourceGroup']);
$left = $start(['--run']);
$right = $start(['--run']);
$check('RUN-PASS' === $finish($left) && 'RUN-PASS' === $finish($right), 'parallel production runs complete');
$runner->execute(200);
$history = $archives->history($institutionNative);
$check(3 === count($history), 'parallel runs create exactly one new institution release');
$check(0 === $scalar('SELECT COUNT(*) FROM (SELECT GROUP_ID,DEADLINE_AT,ORDER_LINE_ID FROM mf_production_line GROUP BY GROUP_ID,DEADLINE_AT,ORDER_LINE_ID HAVING COUNT(*)>1) d'), 'concurrency does not duplicate L1 print rows');
$verify($history[2]);

// The same Idempotency-Key revokes once; a file missing at build time fails, then the same snapshot recovers.
$key = 'institution-native-renew-0001';
$renewed = $renew->execute($organizer, $latest->publicId, $key);
$check($renewed->generation === $renew->execute($organizer, $latest->publicId, $key)->generation, 'renewal replay is stable');
$ticket = $download->link($organizer, $latest->publicId)->ticket;
$db->queryExecute("UPDATE mf_production_institution_archive SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE PUBLIC_ID='" . $sql->forSql($latest->publicId) . "'");
$runner->execute(200);
$check(VersionStatusEnum::EXPIRED === $archives->find($latest->publicId)?->status, 'expiry is persisted');
try {
    $download->open($ticket);
    throw new LogicException('Expired ticket was accepted.');
} catch (HttpException $error) {
    $check('ARCHIVE_NOT_FOUND' === $error->getMessage(), 'expired download denied');
}
$frozen = $export->execute($organizer, $latest->publicId)->content;
$renew->execute($organizer, $latest->publicId, 'institution-native-rebuild-0001');
$line = $snapshot->lines($latest)[0];
$original = $services->get(OriginalFilesInterface::class)->originals([$line->photoId])[$line->photoId];
$temporary = $original->absolutePath . '.institution-e2e-hidden';
$check(rename($original->absolutePath, $temporary), 'temporarily hide one isolated original');
try {
    // Media returns persisted original metadata; a missing disk file is detected by the ZIP writer.
    // Drive the existing five-minute retry schedule without sleeping, keeping the production attempt limit.
    for ($attempt = 1; $attempt <= 3; ++$attempt) {
        $runner->execute(200);
        $failed = $archives->find($latest->publicId);
        $check(null !== $failed && VersionStatusEnum::READY !== $failed->status && $attempt === $failed->attempts, 'missing original never yields ready');
        try {
            $download->link($organizer, $latest->publicId);
            throw new LogicException('A partial ZIP was downloadable.');
        } catch (HttpException $error) {
            $check('ARCHIVE_NOT_FOUND' === $error->getMessage(), 'partial ZIP download is denied');
        }
        if ($attempt < 3) {
            $db->queryExecute("UPDATE mf_production_institution_archive SET LEASE_UNTIL=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE PUBLIC_ID='" . $sql->forSql($latest->publicId) . "' AND STATUS='pending'");
        }
    }
    $check(VersionStatusEnum::FAILED === $failed->status && 'ARCHIVE_FAILED' === $failed->error, 'three failed writes end in an explicit error');
} finally {
    rename($temporary, $original->absolutePath);
}
$renew->execute($organizer, $latest->publicId, 'institution-native-rebuild-0002');
$runner->execute(200);
$recovered = $archives->find($latest->publicId);
$check(null !== $recovered && VersionStatusEnum::READY === $recovered->status && $frozen === $export->execute($organizer, $latest->publicId)->content, 'same frozen paths and CSV recover');
$verify($recovered);
$check(3 === count($archives->history($institutionNative)), 'renewal and recovery add no new assignment');
$check($beforeHash === hash_file('sha256', $storage->absolutePath((string)$first->archivePath)), 'first issued ZIP stays unchanged');

// Fail the actual key INSERT after the effect, then verify transaction rollback and retry on real MySQL.
$failRequest = static function(Closure $operation) use ($db, $check): void {
    $db->queryExecute("CREATE TRIGGER mf_e2e_institution_request_failure BEFORE INSERT ON mf_production_institution_request FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected institution request failure'");
    try {
        try {
            $operation();
            $check(false, 'the request INSERT failure must reach the caller');
        } catch (ProductionStorageException) {
            // The repository translates the injected database error; its effect must be rolled back below.
        }
    } finally {
        $db->queryExecute('DROP TRIGGER mf_e2e_institution_request_failure');
    }
};
$generation = $recovered->generation;
$atomicRenewKey = 'institution-native-atomic-renew';
$failRequest(static fn(): mixed => $renew->execute($organizer, $latest->publicId, $atomicRenewKey));
$check($generation === $archives->find($latest->publicId)?->generation && null === $archives->request($organizer, hash('sha256', $atomicRenewKey)), 'failed request persistence rolls back generation and key');
$check($generation + 1 === $renew->execute($organizer, $latest->publicId, $atomicRenewKey)->generation
    && $generation + 1 === $renew->execute($organizer, $latest->publicId, $atomicRenewKey)->generation, 'renewal retries once after a failed key INSERT');

institutionProductionFixture('institution-add-timely', (string)$fixture['sourceGroup']);
$supplements = $services->get(CreateInstitutionSupplementUseCase::class);
$atomicSupplementKey = 'institution-native-atomic-supplement';
$sourceRows = $scalar('SELECT COUNT(*) FROM mf_production_line');
$failRequest(static fn(): mixed => $supplements->execute($organizer, (string)$fixture['institutionId'], $atomicSupplementKey));
$check(3 === count($archives->history($institutionNative)) && null === $archives->request($organizer, hash('sha256', $atomicSupplementKey)), 'failed key INSERT rolls back the delivery release');
$committedRows = $scalar('SELECT COUNT(*) FROM mf_production_line');
$check($committedRows > $sourceRows, 'the independently committed L1 job stays reusable');
$supplement = $supplements->execute($organizer, (string)$fixture['institutionId'], $atomicSupplementKey);
$check($supplement->id === $supplements->execute($organizer, (string)$fixture['institutionId'], $atomicSupplementKey)->id
    && $supplement->prints * 2 === $first->prints && $committedRows === $scalar('SELECT COUNT(*) FROM mf_production_line'), 'supplement retry returns one release with only new rows and no duplicate L1 print');
$runner->execute(200);
$savedSupplement = $archives->find($supplement->id);
$check(null !== $savedSupplement, 'the supplemental delivery is persisted');
$verify($savedSupplement);
$check(5 === count($archives->history($institutionNative)), 'the next full release includes the new supplement alongside previous jobs');

// REV-01: renewal never treats an unpublished, stale composition as an already issued archive.
institutionProductionFixture('institution-add-open', (string)$fixture['sourceGroup']);
institutionProductionFixture('institution-close-new', (string)$fixture['sourceGroup']);
$institution = $services->get(PrintInstitutionDirectoryInterface::class)->find((string)$fixture['institutionId']);
$check(null !== $institution, 'the review regression institution exists');
$unpublished = $snapshot->capture($institution);
$check(null !== $unpublished && null === $unpublished->firstReadyAt, 'the new full composition has not been published');
institutionProductionFixture('institution-add-open', (string)$fixture['sourceGroup']);
$builder = $services->get(InstitutionArchiveBuilder::class);
for ($generation = 1; $generation <= 2; ++$generation) {
    $candidate = $archives->find($unpublished->publicId);
    $check(null !== $candidate && !$builder->build($candidate), 'an open group prevents the unpublished full archive');
    $failed = $archives->find($unpublished->publicId);
    $check(null !== $failed && VersionStatusEnum::FAILED === $failed->status && 'COMPOSITION_CHANGED' === $failed->error
        && null === $failed->firstReadyAt, 'renewal does not bypass the first publication scope');
    try {
        $download->link($organizer, $unpublished->publicId);
        throw new LogicException('An incomplete unpublished archive was downloadable.');
    } catch (HttpException $error) {
        $check('ARCHIVE_NOT_FOUND' === $error->getMessage(), 'the stale composition cannot be downloaded');
    }
    if (1 === $generation) {
        $renew->execute($organizer, $unpublished->publicId, 'institution-review-unpublished-renew');
    }
}
$check($first->bytes === $download->open($download->link($organizer, $first->publicId)->ticket)->bytes, 'the previous issued archive remains available');
institutionProductionFixture('institution-close-new', (string)$fixture['sourceGroup']);
$runner->execute(200);
$history = $archives->history($institutionNative);
$complete = $history[array_key_last($history)];
$check(VersionStatusEnum::READY === $complete->status && null !== $complete->firstReadyAt, 'closing the new composition produces a full published archive');
$verify($complete);
echo "REV-01 unpublished scope after renewal passed\n";

// REV-03: early renewal keeps the expired path transactionally until a worker removes it.
$issued = $archives->find($latest->publicId);
$check(null !== $issued && null !== $issued->archivePath, 'the issued archive still has a real file');
$obsoletePath = $issued->archivePath;
$frozen = $export->execute($organizer, $issued->publicId)->content;
$db->queryExecute("UPDATE mf_production_institution_archive SET EXPIRES_AT=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE PUBLIC_ID='" . $sql->forSql($issued->publicId) . "'");
$renew->execute($organizer, $issued->publicId, 'institution-review-before-cleanup');
$pending = $archives->find($issued->publicId);
$check(null !== $pending && $obsoletePath === $pending->obsoletePath && $storage->exists($obsoletePath)
    && $issued->firstReadyAt == $pending->firstReadyAt, 'renewal retains both the old file path and first publication');

// REV-02: even an interrupted terminal lease can be reclaimed without another ZIP build.
$clock = $services->get(ClockInterface::class);
$ids = $services->get(ProductionIdGeneratorInterface::class);
for ($attempt = 1; $attempt <= 5; ++$attempt) {
    $owner = $ids->uuid();
    $path = $storage->archivePath($issued->institutionPublicId, $owner);
    $now = $clock->now();
    $check($archives->claim($issued->publicId, $owner, $path, $now, $now->modify('+30 minutes')), 'an expired exhausted lease remains reclaimable');
    $check(!$archives->claim($issued->publicId, $ids->uuid(), $path, $now, $now->modify('+30 minutes')), 'an active lease is protected');
    $db->queryExecute("UPDATE mf_production_institution_archive SET LEASE_UNTIL=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE PUBLIC_ID='" . $sql->forSql($issued->publicId) . "'");
}
$runner->execute(200);
$exhausted = $archives->find($issued->publicId);
$check(null !== $exhausted && VersionStatusEnum::FAILED === $exhausted->status && 4 === $exhausted->attempts
    && 'ARCHIVE_FAILED' === $exhausted->error, 'the interrupted exhausted lease reaches a recoverable terminal state');
foreach ($archives->due($clock->now(), 200) as $due) {
    $check($due->publicId !== $issued->publicId, 'the terminal archive no longer occupies the due batch');
}
echo "REV-02 interrupted exhausted lease passed\n";
$renew->execute($organizer, $issued->publicId, 'institution-review-exhausted-renew');
$runner->execute(200);
$rebuilt = $archives->find($issued->publicId);
$check(null !== $rebuilt && VersionStatusEnum::READY === $rebuilt->status && null === $rebuilt->obsoletePath
    && !$storage->exists($obsoletePath) && $issued->firstReadyAt == $rebuilt->firstReadyAt
    && $frozen === $export->execute($organizer, $issued->publicId)->content, 'early renewal cleans the obsolete ZIP and restores the frozen archive');
$verify($rebuilt);
echo "REV-03 renewal before expiry cleanup passed\n";

Loader::includeModule('sprint.migration');
require_once '/app/public/local/php_interface/migrations.foundation/Version20261110180001.php';
(new Version20261110180001())->up();
try {
    (new Version20261110180001())->down();
    throw new LogicException('Destructive down was accepted.');
} catch (RuntimeException $error) {
    $check(str_contains($error->getMessage(), 'snapshots exist'), 'down refuses while snapshots exist');
}
$check($beforeRows > 0, 'the institution has source print rows');
echo "Institution production integration passed\n";
