<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Data\ManagedCache;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Repository\UploadedFileOwnerRepository;
use Rebit\Share\Domain\File\Service\UploadedFileOwnershipService;
use Sprint\Migration\Version20260911130001;

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    require __DIR__ . '/w03/bootstrap.php';
    $mode = $argv[1] ?? '';
    $check(in_array($mode, ['write', 'read-after-restart'], true), 'Choose write or read-after-restart.');
    $connection = Application::getConnection();
    $check(
        'rabit_w03' === $connection->getDatabase() && 'rabit-w03-mysql' === $connection->getHost(),
        'Refusing a database outside the dedicated W03 fixture.',
    );
    require $apiRoot . '/public/local/php_interface/migrations.foundation/Version20260911130001.php';
    $migration = (new ReflectionClass(Version20260911130001::class))
        ->newInstanceWithoutConstructor()
    ;
    $cache = Application::getInstance()->getManagedCache();
    $repository = new UploadedFileOwnerRepository();
    $service = new UploadedFileOwnershipService($repository, $cache);
    $cacheKey = 'rebit_share_upload_owner_v2_1001';

    if ('write' === $mode) {
        $connection->queryExecute('DROP TABLE IF EXISTS rebit_share_uploaded_file_owner');
        $migration->up();
        $migration->up();
        $migration->down();
        $check(!$connection->isTableExists('rebit_share_uploaded_file_owner'), 'Empty rollback failed.');
        $migration->up();
        $check(null === $service->resolve(9999), 'Legacy file was assigned an invented owner.');
        $service->remember(1001, 501, 'rebit.share');
        $check(
            ['userId' => 501, 'moduleId' => 'rebit.share'] === $service->resolve(1001),
            'Owner was not resolved after a durable write.',
        );
        $check($service->isOwnedBy(1001, 501, 'rebit.share'), 'Actual owner denied.');
        $check(!$service->isOwnedBy(1001, 502, 'rebit.share'), 'Another user accepted.');
        $check(!$service->isOwnedBy(1001, 501, 'rebit.auth'), 'Another module accepted.');
        $conflict = false;
        try {
            $service->remember(1001, 502, 'rebit.auth');
        } catch (FileUploadFailedException) {
            $conflict = true;
        }
        $check($conflict, 'Duplicate file ID reassigned ownership.');
        $rollbackRefused = false;
        try {
            $migration->down();
        } catch (RuntimeException) {
            $rollbackRefused = true;
        }
        $check($rollbackRefused, 'Rollback destroyed ownership records.');
        ManagedCache::finalize();
        $check(
            $cache->getImmediate(3600, $cacheKey) === ['userId' => 501, 'moduleId' => 'rebit.share'],
            'ManagedCache lifecycle did not persist its entry.',
        );
    } else {
        $check($cache->read(3600, $cacheKey), 'Cache did not survive a PHP process restart.');
        $check($service->isOwnedBy(1001, 501, 'rebit.share'), 'Durable owner failed after restart.');
        $cache->clean($cacheKey);
        $check(!$cache->read(3600, $cacheKey), 'The explicit ownership cache clear failed.');
        $service = new UploadedFileOwnershipService($repository, new ManagedCache());
        $check($service->isOwnedBy(1001, 501, 'rebit.share'), 'Database fallback failed after cache clear.');
        $check(!$service->isOwnedBy(1001, 502, 'rebit.share'), 'A different user gained access.');
        $check(!$service->isOwnedBy(9999, 501, 'rebit.share'), 'Unowned legacy file was accepted.');
        $brokenCache = new class extends ManagedCache {
            public function read(mixed $ttl, mixed $uniqueId, mixed $tableId = false): bool
            {
                throw new RuntimeException('Fixture cache offline.');
            }

            public function clean(mixed $uniqueId, mixed $tableId = false): void
            {
                throw new RuntimeException('Fixture cache offline.');
            }
        };
        $withoutCache = new UploadedFileOwnershipService($repository, $brokenCache);
        $check($withoutCache->isOwnedBy(1001, 501, 'rebit.share'), 'Offline cache changed the owner.');
        $withoutCache->remember(1002, 502, 'rebit.auth');
        $check($withoutCache->isOwnedBy(1002, 502, 'rebit.auth'), 'Offline cache prevented a durable write.');
        $cache->clean($cacheKey);
        $cache->read(3600, $cacheKey);
        $cache->set($cacheKey, ['userId' => 'invalid', 'moduleId' => null]);
        $service = new UploadedFileOwnershipService($repository, $cache);
        $check($service->isOwnedBy(1001, 501, 'rebit.share'), 'Malformed cache prevented database fallback.');
        $row = $connection->query('SELECT USER_ID, MODULE_ID FROM rebit_share_uploaded_file_owner WHERE FILE_ID=1001')->fetch();
        $check(
            501 === (int)$row['USER_ID'] && 'rebit.share' === $row['MODULE_ID'],
            'The duplicate write changed the authoritative owner.',
        );
    }
    if ('read-after-restart' === $mode) {
        $connection->queryExecute('RENAME TABLE rebit_share_uploaded_file_owner TO w03_unavailable_owner_fixture');
        try {
            $unavailable = false;
            try {
                $service->isOwnedBy(9998, 501, 'rebit.share');
            } catch (FileUploadFailedException $exception) {
                $unavailable = true;
                $check('Не удалось проверить владельца файла.' === $exception->getMessage(), 'Infrastructure message escaped.');
            }
            $check($unavailable, 'A missing ownership table did not fail closed.');
        } finally {
            $connection->queryExecute('RENAME TABLE w03_unavailable_owner_fixture TO rebit_share_uploaded_file_owner');
        }
    }
    echo json_encode([
        'status' => 'PASS',
        'mode' => $mode,
        'phpVersion' => PHP_VERSION,
        'database' => 'rabit_w03',
        'realBitrixOrm' => true,
        'realManagedCache' => true,
        'legacyUnownedDenied' => true,
        'productionSettingsLoaded' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL', 'class' => $exception::class,
        'error' => $exception->getMessage(),
        'previous' => $exception->getPrevious()?->getMessage(),
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
}
