<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Morefoto\Commerce\Presentation\Controller\CatalogController;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Sprint\Migration\Version20260912220001;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $name);
    }
    $checks[] = $name;
};
$quiet = static function(callable $operation): void {
    ob_start();
    try {
        $operation();
    } finally {
        ob_end_clean();
    }
};
try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    $sql = $fixture['connection'];
    ServiceLocator::getInstance()->registerByModuleSettings('main');
    require_once '/kernel/modules/highloadblock/install/index.php';
    $assert((new highloadblock())->InstallDB(), 'native highloadblock installation');
    foreach (['morefoto.access', 'morefoto.commerce'] as $module) {
        symlink('/app/public/local/modules/' . $module, $fixture['documentRoot'] . '/local/modules/' . $module);
    }
    $quiet(static function(): void {
        foreach (['20260323120001', '20260326120008', '20260911120001', '20260911200001', '20260911220001', '20260912220001'] as $id) {
            require_once '/app/public/local/php_interface/migrations.foundation/Version' . $id . '.php';
            $class = 'Sprint\Migration\Version' . $id;
            (new $class())->up();
        }
        $migration = new Version20260912220001();
        $migration->up();
        $migration->down();
        $migration->down();
        $migration->up();
    });
    require '/app/public/local/modules/morefoto.commerce/install/index.php';
    (new Morefoto_Commerce())->DoInstall();
    $assert(Loader::includeModule('morefoto.access') && Loader::includeModule('morefoto.commerce'), 'native module Loader after merged B1 and E1');
    $services = ServiceLocator::getInstance();
    $assert($services->get(CatalogController::class) instanceof CatalogController, 'native E2 controller DI resolves');
    require __DIR__ . '/fixtures/e2/http.php';
    $users = new UserRepository();
    $newUser = static function(string $name, ?string $role = null) use ($sql): int {
        $writer = new CUser();
        $password = bin2hex(random_bytes(24));
        $id = $writer->Add(['LOGIN' => $name . '@example.invalid', 'EMAIL' => $name . '@example.invalid', 'NAME' => $name, 'PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password, 'ACTIVE' => 'Y']);
        if (false === $id || 0 >= (int)$id) {
            throw new RuntimeException('Cannot create fixture identity.');
        }
        if (null !== $role) {
            $sql->query("INSERT INTO b_hlbd_mf_staff_profile (UF_USER_ID,UF_ROLE,UF_ACTIVE,UF_REVISION,UF_ACCESS_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES ({$id},'{$role}',1,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        }

        return (int)$id;
    };
    $actor = $newUser('organizer');
    $services->get(BootstrapOrganizerUseCase::class)->execute($actor);
    $other = $newUser('another-organizer', 'organizer');
    $token = 'E2-Organizer';
    $otherToken = 'E2-OtherOrganizer';
    $users->updateToken($actor, $token, DateTime::createFromTimestamp(time() + 3600));
    $users->updateToken($other, $otherToken, DateTime::createFromTimestamp(time() + 3600));
    $path = '/api/v1/catalog/products';
    $product = ['name' => 'Товар Б', 'description' => 'Кириллица 🖼', 'kind' => 'physical', 'price' => 12500, 'printCount' => 1, 'format' => '10×15', 'unit' => 'шт.', 'staffDiscount' => true, 'active' => true];
    $json = json_encode($product, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $key = str_repeat('a', 32);
    $get = static fn(array $query = []): array => E2Http::request('GET', $path, $token, query: $query);
    $error = static function(array $response, int $status, string $code, string $name) use ($assert): void {
        $assert($status === $response['status'] && $code === ($response['body']['error']['code'] ?? null), $name);
        $assert(isset($response['body']['error']['message'], $response['body']['meta']['requestId']) && !isset($response['body']['data'], $response['body']['error']['debug']) && 'no-store' === $response['cacheControl'], $name . ': canonical private error envelope');
    };
    $stage = 'native-routing-and-auth';
    $empty = $get();
    $assert(200 === $empty['status'] && ['items' => [], 'revision' => 1] === $empty['body']['data'] && ['page' => 1, 'pageSize' => 50, 'total' => 0] === $empty['body']['meta'], 'COM-01 actual empty catalogue and common pagination defaults');
    $assert('no-store' === $empty['cacheControl'], 'successful private responses are not cacheable');
    $error(E2Http::request('GET', $path), 401, 'UNAUTHORIZED', 'missing bearer');
    $error(E2Http::request('GET', $path, 'invalid'), 401, 'UNAUTHORIZED', 'invalid bearer');
    foreach ([null, 'teacher', 'head', 'curator'] as $role) {
        $id = $newUser('role-' . ($role ?? 'none'), $role);
        $roleToken = 'E2-role-' . ($role ?? 'none');
        $users->updateToken($id, $roleToken, DateTime::createFromTimestamp(time() + 3600));
        $error(E2Http::request('GET', $path, $roleToken), 403, 'FORBIDDEN', 'role denied: ' . ($role ?? 'no profile'));
        $error(E2Http::request('POST', $path, $roleToken, $json, $key), 403, 'FORBIDDEN', 'mutation denied: ' . ($role ?? 'no profile'));
    }
    $assert(404 === E2Http::request('DELETE', $path . '/11111111-1111-4111-8111-111111111111', $token)['status'], 'no delete route exists');
    $stage = 'strict-http-input';
    $error(E2Http::request('POST', $path, $token, '{', $key), 400, 'MALFORMED_JSON', 'malformed JSON');
    foreach (['null', '[]', '{}'] as $raw) {
        $error(E2Http::request('POST', $path, $token, $raw, $key), 422, 'VALIDATION_FAILED', 'invalid object shape ' . $raw);
    }
    foreach (['price' => '12500', 'active' => 1, 'description' => null, 'actorId' => $actor] as $field => $value) {
        $bad = $product;
        $bad[$field] = $value;
        $error(E2Http::request('POST', $path, $token, json_encode($bad, JSON_THROW_ON_ERROR), $key), 422, 'VALIDATION_FAILED', 'strict JSON field ' . $field);
    }
    $error(E2Http::request('POST', $path, $token, $json), 422, 'VALIDATION_FAILED', 'required idempotency key');
    $error(E2Http::request('POST', $path, $token, $json, 'bad'), 422, 'VALIDATION_FAILED', 'invalid idempotency key');
    $error(E2Http::request('POST', $path, $token, $json, $key, ['price' => '1']), 422, 'VALIDATION_FAILED', 'query cannot override JSON');
    $error(E2Http::request('POST', $path, $token, $json, $key, contentType: 'text/plain'), 422, 'VALIDATION_FAILED', 'JSON content type required');
    $error($get(['unexpected' => '1']), 422, 'VALIDATION_FAILED', 'unknown query rejected');
    $error($get(['pageSize' => '101']), 422, 'VALIDATION_FAILED', 'page size bound');
    $assert(0 === $get()['body']['meta']['total'], 'invalid and forbidden requests left catalogue empty');

    $stage = 'durable-create-and-patch';
    $created = E2Http::request('POST', $path, $token, $json, $key);
    $id = $created['body']['data']['id'] ?? '';
    $assert(201 === $created['status'] && 36 === strlen($id) && 2 === $created['body']['data']['revision'] && $path . '/' . $id === $created['location'], 'COM-02 creates UUID with revision and Location');
    $replay = E2Http::request('POST', $path, $token, json_encode(array_reverse($product, true), JSON_THROW_ON_ERROR), strtoupper($key));
    $assert($created === $replay, 'normalized same-key create replays exact result status and Location');
    $bad = $product;
    $bad['price'] = 1;
    $error(E2Http::request('POST', $path, $token, json_encode($bad, JSON_THROW_ON_ERROR), $key), 409, 'IDEMPOTENCY_CONFLICT', 'key mismatch rejected');
    $otherCreate = E2Http::request('POST', $path, $otherToken, $json, $key);
    $assert(201 === $otherCreate['status'] && $id !== $otherCreate['body']['data']['id'], 'idempotency key is scoped by actor');
    $secondId = $otherCreate['body']['data']['id'];
    $patchJson = json_encode(['revision' => 3, 'name' => 'Товар А', 'price' => 0, 'description' => '', 'staffDiscount' => false, 'active' => false], JSON_THROW_ON_ERROR);
    $patched = E2Http::request('PATCH', $path . '/' . $id, $token, $patchJson, $key);
    $assert(200 === $patched['status'] && ['id' => $id, 'revision' => 4] === $patched['body']['data'], 'COM-03 PATCH scopes same key by method/resource');
    $assert($patched === E2Http::request('PATCH', $path . '/' . $id, $token, $patchJson, $key), 'PATCH replay precedes now-stale global revision check');
    $all = $get();
    $item = $all['body']['data']['items'][0];
    $assert($id === $item['id'] && 'Товар А' === $item['name'] && 0 === $item['price'] && '' === $item['description'] && false === $item['staffDiscount'] && false === $item['active'], 'real mapper uses name ordering and preserves false zero empty');
    $assert(['id', 'name', 'description', 'kind', 'price', 'printCount', 'format', 'unit', 'staffDiscount', 'active'] === array_keys($item), 'exact Product projection without persistence or auth fields');
    $one = $get(['page' => '1', 'pageSize' => '1']);
    $two = $get(['page' => '2', 'pageSize' => '1']);
    $assert($id === $one['body']['data']['items'][0]['id'] && $secondId === $two['body']['data']['items'][0]['id'] && 2 === $two['body']['meta']['total'], 'native pagination is stable name asc then internal id');
    $error(E2Http::request('PATCH', $path . '/' . $secondId, $token, '{"revision":3,"active":false}', str_repeat('b', 32)), 409, 'REVISION_CONFLICT', 'catalogue revision is global across products');
    $error(E2Http::request('PATCH', $path . '/11111111-1111-4111-8111-111111111111', $token, '{"revision":4,"active":false}', str_repeat('b', 32)), 404, 'NOT_FOUND', 'missing product');
    $error(E2Http::request('PATCH', $path . '/bad', $token, '{"revision":4,"active":false}', str_repeat('b', 32)), 422, 'VALIDATION_FAILED', 'strict path UUID');
    $error(E2Http::request('PATCH', $path . '/' . $id, $token, '{"revision":4,"active":null}', str_repeat('b', 32)), 422, 'VALIDATION_FAILED', 'PATCH null does not clear nonnullable field');
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ACTIVE=0 WHERE UF_USER_ID={$actor}");
    $error(E2Http::request('POST', $path, $token, $json, $key), 403, 'FORBIDDEN', 'stored replay does not bypass disabled staff');
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ACTIVE=1 WHERE UF_USER_ID={$actor}");
    $users->clearToken($actor);
    $error(E2Http::request('POST', $path, $token, $json, $key), 401, 'UNAUTHORIZED', 'stored replay does not bypass revoked token');
    $users->updateToken($actor, $token, DateTime::createFromTimestamp(time() + 3600));

    $stage = 'rollback-and-unavailable';
    $before = $get();
    $assert(false !== $sql->query("CREATE TRIGGER e2_fail_idempotency BEFORE INSERT ON mf_catalog_idempotency FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture storage failure'"), 'install isolated failure trigger');
    $error(E2Http::request('POST', $path, $token, $json, str_repeat('c', 32)), 503, 'CATALOG_UNAVAILABLE', 'idempotency persistence failure rolls back mutation');
    $assert($before === $get(), 'failed response persistence leaves product and revision unchanged');
    $sql->query('DROP TRIGGER e2_fail_idempotency');
    $assert(201 === E2Http::request('POST', $path, $token, $json, str_repeat('c', 32))['status'], 'failed key can retry successfully');
    $sql->query('RENAME TABLE mf_access_state TO mf_access_state_e2_missing');
    $error($get(), 503, 'ACCESS_UNAVAILABLE', 'missing access state fails closed');
    $sql->query('RENAME TABLE mf_access_state_e2_missing TO mf_access_state');

    $stage = 'independent-workers';
    $jobsDir = sys_get_temp_dir() . '/e2-jobs-' . bin2hex(random_bytes(6));
    mkdir($jobsDir, 0700);
    $launch = static function(string $name, string $requestKey, ?string $pause = null) use ($jobsDir, $fixture, $path, $token, $json): array {
        $jobFile = $jobsDir . '/' . $name . '.json';
        $job = ['documentRoot' => $fixture['documentRoot'], 'method' => 'POST', 'path' => $path, 'token' => $token, 'raw' => $json, 'key' => $requestKey];
        if (null !== $pause) {
            $job['pause'] = $pause;
            $job['marker'] = $jobFile . '.marker';
        }
        file_put_contents($jobFile, json_encode($job, JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, '-d', 'short_open_tag=1', '-d', 'date.timezone=UTC', __DIR__ . '/fixtures/e2/worker.php', $jobFile], [['pipe', 'r'], ['file', $jobFile . '.stdout', 'a'], ['file', $jobFile . '.stderr', 'a']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot launch independent PHP worker.');
        }
        fclose($pipes[0]);

        return [$process, $jobFile];
    };
    $awaitMarker = static function(array $worker): void {
        $deadline = microtime(true) + 8;
        while (!file_exists($worker[1] . '.marker')) {
            clearstatcache();
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Worker marker missing: ' . @file_get_contents($worker[1] . '.result') . ' ' . @file_get_contents($worker[1] . '.stderr'));
            }
            usleep(10000);
        }
    };
    $finish = static function(array $worker): array {
        $exit = proc_close($worker[0]);
        $result = json_decode(file_get_contents($worker[1] . '.result'), true, 32, JSON_THROW_ON_ERROR);
        if (0 !== $exit || isset($result['failure'])) {
            throw new RuntimeException('Worker failed: ' . json_encode($result));
        }

        return $result;
    };
    $raceKey = str_repeat('d', 32);
    $before = $get();
    $first = $launch('race-first', $raceKey, 'after');
    $awaitMarker($first);
    $second = $launch('race-second', $raceKey);
    $waiting = false;
    for ($i = 0; $i < 100; ++$i) {
        if (0 < (int)$sql->query('SELECT COUNT(*) C FROM performance_schema.data_lock_waits')->fetch_assoc()['C']) {
            $waiting = true;
            break;
        }
        usleep(10000);
    }
    file_put_contents($first[1] . '.marker.release', 'release');
    $r1 = $finish($first);
    $r2 = $finish($second);
    $assert($waiting, 'real concurrent workers wait on actor authorization lock');
    $assert(201 === $r1['status'] && $r1 === $r2, 'concurrent identical HTTP requests return one durable result');
    $after = $get();
    $assert($before['body']['meta']['total'] + 1 === $after['body']['meta']['total'] && $before['body']['data']['revision'] + 1 === $after['body']['data']['revision'], 'concurrent retry creates one product and one revision');
    $restart = $launch('restarted-worker', $raceKey);
    $assert($r1 === $finish($restart), 'replay survives a fresh PHP process');

    $before = $get();
    $revoked = $launch('prefilter-then-revoke', str_repeat('e', 32), 'before');
    $awaitMarker($revoked);
    $users->clearToken($actor);
    file_put_contents($revoked[1] . '.marker.release', 'release');
    $error($finish($revoked), 401, 'UNAUTHORIZED', 'token revoked after prefilter is rejected under transaction locks');
    $users->updateToken($actor, $token, DateTime::createFromTimestamp(time() + 3600));
    $demoted = $launch('prefilter-then-demote', str_repeat('f', 32), 'before');
    $awaitMarker($demoted);
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ROLE='teacher' WHERE UF_USER_ID={$actor}");
    file_put_contents($demoted[1] . '.marker.release', 'release');
    $error($finish($demoted), 403, 'FORBIDDEN', 'role changed after prefilter is rejected under transaction locks');
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ROLE='organizer' WHERE UF_USER_ID={$actor}");
    $assert($before === $get(), 'authorization races leave catalogue and revision unchanged');
    $assert(0 === (int)$sql->query("SELECT COUNT(*) C FROM mf_catalog_idempotency WHERE IDEMPOTENCY_KEY IN ('" . str_repeat('e', 32) . "','" . str_repeat('f', 32) . "')")->fetch_assoc()['C'], 'denied races create no idempotency receipts');
    try {
        (new Version20260912220001())->down();
        throw new RuntimeException('Rollback unexpectedly removed receipts.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'Idempotency records exist'), 'rollback preserves durable mutation receipts');
    }

    echo json_encode(['wave' => 'E2', 'status' => 'passed', 'checks' => count($checks), 'assertions' => $checks, 'php' => PHP_VERSION, 'bitrix' => $fixture['kernelVersion'], 'mysql' => $sql->server_info,
        'seams' => ['CLI reconstructs native HttpRequest; only php://input is supplied by a test subclass.', 'Each request uses actual main route registry, Router, controller, filters, request factory, serializer and database.', 'Concurrent independent PHP workers reconnect to the same disposable fixture; pause adapter wraps real Access locks to coordinate races.'],
        'externalNetwork' => false, 'hostPorts' => false, 'productionData' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['stage' => $stage, 'checks' => count($checks), 'class' => $exception::class, 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine(), 'cause' => $exception->getPrevious()?->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
