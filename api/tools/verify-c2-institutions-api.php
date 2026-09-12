<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\Server;
use Bitrix\Main\Type\DateTime;
use Morefoto\Access\Application\Assignment\Service\InstitutionAccess;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Application\Institution\UseCase\ListVisibleInstitutionsUseCase;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Request\InstitutionRequestFactory;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Sprint\Migration\Version20260912210001;

$checks = [];
$stage = 'bootstrap';
$runningWorkers = [];
$assert = static function(bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $label);
    }
    $checks[] = $label;
};
$expect = static function(callable $operation, string $label) use ($assert): void {
    try {
        $operation();
    } catch (Throwable) {
        $assert(true, $label);

        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
};
try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    // W02's reduced CLI bootstrap omits main prolog registration; use the native service definitions.
    ServiceLocator::getInstance()->registerByModuleSettings('main');
    $sql = $fixture['connection'];
    $connection = Application::getConnection();
    $autoload = require '/app/vendor/autoload.php';
    $autoload->addPsr4('Morefoto\Access\\', '/app/public/local/modules/morefoto.access/lib/', true);
    foreach (['morefoto.access', 'morefoto.organization'] as $module) {
        symlink('/app/public/local/modules/' . $module, $fixture['documentRoot'] . '/local/modules/' . $module);
    }
    require_once '/kernel/modules/highloadblock/install/index.php';
    $assert((new highloadblock())->InstallDB(), 'native highloadblock installation');
    $stage = 'migrations';
    ob_start();
    try {
        foreach (['Version20260323120001', 'Version20260326120008', 'Version20260911120001', 'Version20260911200001', 'Version20260911210001', 'Version20260912210001'] as $version) {
            require_once '/app/public/local/php_interface/migrations.foundation/' . $version . '.php';
            $class = 'Sprint\Migration\\' . $version;
            (new $class())->up();
        }
        $migration = new Version20260912210001();
        $migration->up();
        $migration->down();
        $assert(!$connection->isTableExists('b_hlbd_mf_institution_assignment'), 'empty down removes C2 assignment schema');
        $assert($connection->isTableExists('b_hlbd_mf_institution') && $connection->isTableExists('b_hlbd_mf_staff_profile'), 'empty C2 down preserves B1 and C1');
        $migration->up();
    } finally {
        ob_end_clean();
    }
    $assert(Loader::includeModule('morefoto.access') && Loader::includeModule('morefoto.organization'), 'native B1/C1 module autoload and C2 DI');
    $tables = ['b_hlbd_mf_institution_assignment', 'b_hlbd_mf_access_change', 'b_hlbd_mf_organization_change', 'mf_institution_operation'];
    foreach ($tables as $table) {
        $assert('InnoDB' === $sql->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetch_assoc()['ENGINE'], $table . ' is transactional');
    }
    $assert(3 === (int)$sql->query("SELECT COUNT(*) C FROM b_hlblock_entity WHERE NAME IN ('MfInstitutionAssignment','MfAccessChange','MfOrganizationChange')")->fetch_assoc()['C'], 'three real native HL metadata records after repeated up/down/up');
    $assert(19 === (int)$sql->query("SELECT COUNT(*) C FROM b_user_field WHERE ENTITY_ID IN (SELECT CONCAT('HLBLOCK_',ID) FROM b_hlblock_entity WHERE NAME IN ('MfInstitutionAssignment','MfAccessChange','MfOrganizationChange'))")->fetch_assoc()['C'], 'nineteen real HL user fields');
    $locator = ServiceLocator::getInstance();
    $listing = $locator->get(ListVisibleInstitutionsUseCase::class);
    $save = $locator->get(SaveInstitutionUseCase::class);
    $requests = $locator->get(InstitutionRequestFactory::class);
    $tokens = $locator->get(TokenResolverInterface::class);
    $users = new UserRepository();
    $staff = [];
    $newUser = static function(string $label, ?string $role, bool $staffActive = true, bool $identityActive = true) use ($sql, $users): int {
        $writer = new CUser();
        $password = 'Native-C2-test-password-123';
        $id = (int)$writer->Add(['LOGIN' => $label . '@example.invalid', 'EMAIL' => $label . '@example.invalid', 'NAME' => $label,
            'PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password, 'ACTIVE' => $identityActive ? 'Y' : 'N']);
        if (1 > $id) {
            throw new RuntimeException('Cannot create disposable Auth identity.');
        }
        if (null !== $role) {
            $active = $staffActive ? 1 : 0;
            $sql->query("INSERT INTO b_hlbd_mf_staff_profile(UF_USER_ID,UF_ROLE,UF_ACTIVE,UF_REVISION,UF_ACCESS_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES({$id},'{$role}',{$active},1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        }
        $users->updateToken($id, 'C2Token' . $id, DateTime::createFromTimestamp(time() + 3600));

        return $id;
    };
    foreach (['organizer', 'curator', 'curator2', 'head', 'teacher', 'emptycurator'] as $label) {
        $staff[$label] = $newUser($label, in_array($label, ['curator2', 'emptycurator'], true) ? 'curator' : $label);
    }
    $staff['inactive'] = $newUser('inactive', 'curator', false);
    $staff['disabled'] = $newUser('disabled', 'curator', true, false);
    $staff['nostaff'] = $newUser('nostaff', null);
    $token = static fn(string $role): string => 'C2Token' . $staff[$role];
    $refresh = static function(string $role) use ($users, $staff): void {
        $users->updateToken($staff[$role], 'C2Token' . $staff[$role], DateTime::createFromTimestamp(time() + 3600));
    };
    $request = static function(string $action, ?string $bearer, mixed $body = null, ?string $id = null, ?string $key = null, array $query = [], string $contentType = 'application/json', ?SaveInstitutionUseCase $override = null) use ($listing, $save, $requests, $tokens): array {
        $values = $_SERVER;
        unset($values['HTTP_AUTHORIZATION']);
        $values['REQUEST_METHOD'] = match ($action) {
            'list' => 'GET', 'create' => 'POST', default => 'PATCH'
        };
        $values['REQUEST_URI'] = '/api/v1/institutions' . (null === $id ? '' : '/' . $id);
        $values['CONTENT_TYPE'] = $contentType;
        $values['HTTP_IDEMPOTENCY_KEY'] = $key ?? bin2hex(random_bytes(16));
        if (null !== $bearer) {
            $values['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
        }
        $server = new Server($values);
        // CLI seam only for php://input; all native request/controller/database behavior remains real.
        $http = new class($server, $query, [], [], []) extends HttpRequest {
            public static string $input = '';

            public static function getInput(): string
            {
                return self::$input;
            }
        };
        $http::$input = is_string($body) ? $body : json_encode($body ?? (object)[], JSON_THROW_ON_ERROR);
        Application::getInstance()->getContext()->initialize($http, new HttpResponse(), $server);
        $controller = new InstitutionController($listing, $override ?? $save, $requests, $tokens);
        $response = $controller->run($action, null === $id ? [] : [['institution_id' => $id]]);
        if (!$response instanceof HttpResponse) {
            $response = new HttpResponse();
        }
        $controller->finalizeResponse($response);
        $status = (int)$response->getStatus();
        if (503 === $status) {
            $failure = (new ReflectionProperty($controller, 'thrownException'))->getValue($controller);
            if ($failure instanceof Throwable) {
                fwrite(STDERR, 'C2 diagnostic ' . $failure::class . ': ' . $failure->getMessage() . ' at ' . basename($failure->getFile()) . ':' . $failure->getLine() . PHP_EOL);
            }
        }

        return [0 === $status ? 200 : $status, json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR), ['cache' => $response->getHeaders()->get('Cache-Control'), 'location' => $response->getHeaders()->get('Location')]];
    };
    $stage = 'http-and-validation';
    [$status, $body] = $request('list', $token('organizer'));
    $assert(200 === $status && [] === $body['data']['items'] && 'a1' === $body['data']['assignmentSignature'] && 0 === $body['meta']['total'], 'real GET returns empty list with actual initial signature');
    $assert(401 === $request('list', null)[0], 'missing Bearer gives 401');
    [$errorStatus, $errorBody, $errorHeaders] = $request('list', 'invalid-token');
    $assert(401 === $errorStatus, 'invalid Bearer gives 401');
    $assert(isset($errorBody['error']['code'], $errorBody['error']['message'], $errorBody['meta']['requestId']) && !isset($errorBody['data']) && 'no-store' === $errorHeaders['cache'], 'error envelope and private no-store match contract');
    $assert(401 === $request('list', $token('disabled'))[0], 'disabled Auth gives 401');
    foreach (['teacher', 'inactive', 'nostaff'] as $role) {
        $assert(403 === $request('list', $token($role))[0], $role . ' has no institution list permission');
    }
    $assert(200 === $request('list', $token('emptycurator'))[0] && [] === $request('list', $token('emptycurator'))[1]['data']['items'], 'active curator with no assignment gets genuine empty scope');
    $createKey = bin2hex(random_bytes(16));
    $createBody = ['name' => 'Первое учреждение', 'address' => 'Адрес'];
    [$status, $body, $headers] = $request('create', $token('organizer'), $createBody, key: $createKey);
    $assert(201 === $status && 1 === $body['data']['revision'] && 'a1' === $body['data']['assignmentSignature'], 'first create without assignment signature succeeds');
    $id = $body['data']['id'];
    $assert('no-store' === $headers['cache'] && '/api/v1/institutions/' . $id === $headers['location'], '201 includes canonical Location and private no-store');
    $firstResponse = $body;
    [$replayStatus, $replay] = $request('create', $token('organizer'), $createBody, key: $createKey);
    $assert(201 === $replayStatus && $firstResponse['data'] === $replay['data'], 'idempotent create replays original 201 and result');
    $assert(409 === $request('create', $token('organizer'), ['name' => 'different'], key: $createKey)[0], 'same key different payload is 409');
    $assert(403 === $request('create', $token('curator'), $createBody)[0], 'read-only role cannot create');
    $assert(404 === $request('update', $token('organizer'), ['name' => 'Missing', 'revision' => 1], '12345678-abcd-4abc-8abc-123456789abc')[0], 'missing valid UUID gives 404');
    $assert(422 === $request('update', $token('organizer'), ['name' => 'Missing', 'revision' => 1], 'not-a-uuid')[0], 'invalid UUID gives 422');
    foreach ([['name' => null], ['name' => 7], ['name' => 'x', 'extra' => true], ['name' => 'x', 'curatorId' => '2'], ['name' => 'x', 'curatorId' => -1], ['name' => 'x', 'replaceAssignments' => 'true'], ['name' => 'x', 'assignmentSignature' => null], ['name' => 'x', 'revision' => 1], [], '[]', '{broken', 'null'] as $invalid) {
        $expected = is_array($invalid) && [] !== $invalid ? 422 : 400;
        $assert($expected === $request('create', $token('organizer'), $invalid)[0], 'strict JSON/type/extra validation ' . count($checks));
    }
    $assert(400 === $request('create', $token('organizer'), $createBody, contentType: 'text/plain')[0], 'non-JSON content type rejected');
    $assert(422 === $request('create', $token('organizer'), $createBody, key: 'short')[0], 'invalid idempotency key rejected');
    $assert(422 === $request('create', $token('organizer'), $createBody, query: ['x' => '1'])[0], 'mutation rejects query-field smuggling');
    foreach ([['page' => '0'], ['pageSize' => '101'], ['q' => ['x']], ['unexpected' => 'x']] as $query) {
        $assert(422 === $request('list', $token('organizer'), query: $query)[0], 'strict list query validation ' . count($checks));
    }
    foreach ([['name' => str_repeat('я', 256)], ['name' => 'x', 'address' => str_repeat('я', 501)], ['name' => ' '], ['name' => "x\0y"], ['name' => 'x', 'curatorId' => 2147483648]] as $invalid) {
        $assert(422 === $request('create', $token('organizer'), $invalid)[0], 'field bounds reject invalid mutation ' . count($checks));
    }
    $assert(1 === (int)$sql->query('SELECT COUNT(*) C FROM b_hlbd_mf_institution')->fetch_assoc()['C'], 'invalid/replayed requests do not leave extra institutions');
    $stage = 'assignments-and-cas';
    $assign = ['revision' => 1, 'curatorId' => $staff['curator'], 'headId' => $staff['head'], 'assignmentSignature' => 'a1'];
    $assignmentKey = bin2hex(random_bytes(16));
    [$status, $assigned] = $request('update', $token('organizer'), $assign, $id, $assignmentKey);
    $assert(200 === $status && 2 === $assigned['data']['revision'] && 'a2' === $assigned['data']['assignmentSignature'], 'assignment update changes entity and global revisions once');
    $assert(401 === $request('list', $token('curator'))[0] && 401 === $request('list', $token('head'))[0], 'all affected old sessions are revoked');
    [$replayStatus, $assignmentReplay] = $request('update', $token('organizer'), $assign, $id, $assignmentKey);
    $assert(200 === $replayStatus && $assigned['data'] === $assignmentReplay['data'], 'assignment idempotency replay returns saved response before stale version/signature checks');
    $refresh('curator');
    $refresh('head');
    foreach (['curator', 'head'] as $role) {
        [$status, $list] = $request('list', $token($role));
        $assert(200 === $status && 1 === count($list['data']['items']) && $id === $list['data']['items'][0]['id'], $role . ' reads only assigned institution');
        $assert(403 === $request('update', $token($role), ['revision' => 2, 'name' => 'forbidden'], $id)[0], $role . ' cannot edit even own institution');
    }
    $assert([] === $request('list', $token('emptycurator'))[1]['data']['items'], 'foreign institutions absent from scope-filtered SQL list');
    $assert(409 === $request('update', $token('organizer'), ['revision' => 1, 'name' => 'stale'], $id)[0], 'stale institution revision rejected');
    $assert(409 === $request('update', $token('organizer'), ['revision' => 2, 'name' => 'must rollback', 'curatorId' => $staff['curator2'], 'assignmentSignature' => 'a1', 'replaceAssignments' => true], $id)[0], 'stale assignment signature rolls back preceding entity write');
    $assert(409 === $request('update', $token('organizer'), ['revision' => 2, 'curatorId' => $staff['curator2'], 'assignmentSignature' => 'a2'], $id)[0], 'occupied assignment requires explicit replacement');
    $assert(422 === $request('update', $token('organizer'), ['revision' => 2, 'curatorId' => $staff['teacher'], 'assignmentSignature' => 'a2', 'replaceAssignments' => true], $id)[0], 'wrong assignee role rejected');
    $assert(422 === $request('update', $token('organizer'), ['revision' => 2, 'curatorId' => $staff['inactive'], 'assignmentSignature' => 'a2', 'replaceAssignments' => true], $id)[0], 'inactive assignee rejected');
    $actual = $sql->query("SELECT ID,UF_NAME,UF_REVISION FROM b_hlbd_mf_institution WHERE UF_PUBLIC_ID='{$id}'")->fetch_assoc();
    $internalId = (int)$actual['ID'];
    $assert('Первое учреждение' === $actual['UF_NAME'] && 2 === (int)$actual['UF_REVISION'], 'rejected changes preserve entity fields/revision');
    $stage = 'fault-rollback';
    $identities = $locator->get(IdentityGatewayInterface::class);
    $fault = new class($identities) implements IdentityGatewayInterface {
        public function __construct(private readonly IdentityGatewayInterface $inner) {}

        public function findActive(int $id): ?IdentityOutputDto
        {
            return $this->inner->findActive($id);
        }

        public function lockActive(int $id): ?IdentityOutputDto
        {
            return $this->inner->lockActive($id);
        }

        public function revokeSessions(int $id): void
        {
            $this->inner->revokeSessions($id);
            throw new RuntimeException('Injected failure after native Auth write.');
        }
    };
    $faultAccess = new InstitutionAccess($locator->get(InstitutionAssignmentRepository::class), $locator->get(StaffProfileRepository::class), $locator->get(StaffAuthorization::class), $fault, $tokens);
    $faultSave = new SaveInstitutionUseCase($locator->get(InstitutionRepository::class), $locator->get(InstitutionOperationRepository::class), $faultAccess, $locator->get(InstitutionTransactionInterface::class));
    $snapshot = static function() use ($sql, $tables): string {
        $state = [];
        foreach (array_merge(['b_hlbd_mf_institution', 'b_hlbd_mf_staff_profile', 'mf_access_state', 'b_uts_user'], $tables) as $table) {
            $state[$table] = $sql->query('SELECT * FROM ' . $table)->fetch_all(MYSQLI_ASSOC);
        }

        return json_encode($state, JSON_THROW_ON_ERROR);
    };
    $beforeFault = $snapshot();
    $replace = ['revision' => 2, 'name' => 'rolled back', 'curatorId' => $staff['curator2'], 'assignmentSignature' => 'a2', 'replaceAssignments' => true];
    $assert(503 === $request('update', $token('organizer'), $replace, $id, override: $faultSave)[0], 'native Auth failure surfaces as service unavailable');
    $assert($beforeFault === $snapshot(), 'entity/assignments/staff revisions/global state/history/operations/tokens all roll back');
    $replace['name'] = 'Изменённое учреждение';
    [$status, $replaced] = $request('update', $token('organizer'), $replace, $id);
    $assert(200 === $status && 3 === $replaced['data']['revision'] && 'a3' === $replaced['data']['assignmentSignature'], 'explicit occupied replacement succeeds');
    $assert(401 === $request('list', $token('curator'))[0] && 401 === $request('list', $token('curator2'))[0], 'both previous and replacement assignee sessions revoked');
    $refresh('curator');
    $refresh('curator2');
    $assert([] === $request('list', $token('curator'))[1]['data']['items'], 'previous assignee loses scope after fresh login');
    $assert(1 === count($request('list', $token('curator2'))[1]['data']['items']), 'replacement assignee obtains current scope after fresh login');
    [$status, $removed] = $request('update', $token('organizer'), ['revision' => 3, 'curatorId' => null, 'assignmentSignature' => 'a3', 'replaceAssignments' => true], $id);
    $assert(200 === $status && 'a4' === $removed['data']['assignmentSignature'], 'explicit null removes assignment without placeholder row');
    $assert(0 === (int)$sql->query("SELECT COUNT(*) C FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID={$internalId} AND UF_ROLE='curator'")->fetch_assoc()['C'], 'removed slot is absent in native table');
    $stage = 'real-concurrency';
    $raceDirectory = $fixture['documentRoot'] . '/c2-races';
    mkdir($raceDirectory, 0700);
    $wait = static function(callable $condition, string $label) use ($assert, $sql): void {
        $deadline = microtime(true) + 15;
        do {
            clearstatcache();
            if ($condition()) {
                $assert(true, $label);

                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        fwrite(STDERR, 'DB wait diagnostic: ' . json_encode($sql->query('SELECT REQUESTING_THREAD_ID,BLOCKING_THREAD_ID FROM performance_schema.data_lock_waits')->fetch_all(MYSQLI_ASSOC), JSON_THROW_ON_ERROR) . PHP_EOL);
        fwrite(STDERR, 'DB transaction diagnostic: ' . json_encode($sql->query('SELECT trx_mysql_thread_id,trx_state FROM information_schema.innodb_trx')->fetch_all(MYSQLI_ASSOC), JSON_THROW_ON_ERROR) . PHP_EOL);
        throw new RuntimeException('Timed out: ' . $label);
    };
    $spawn = static function(array $job) use ($raceDirectory, $fixture, &$runningWorkers): array {
        $prefix = $raceDirectory . '/worker-' . count($runningWorkers);
        $job['ready'] = $prefix . '.ready';
        $job['result'] = $prefix . '.result.json';
        $job['log'] = $prefix . '.log';
        file_put_contents($prefix . '.json', json_encode($job, JSON_THROW_ON_ERROR));
        $process = proc_open(
            [PHP_BINARY, '-d', 'short_open_tag=1', '-d', 'date.timezone=UTC', __DIR__ . '/fixtures/c2/worker.php', $fixture['documentRoot'], $prefix . '.json'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $job['log'], 'a'], 2 => ['file', $job['log'], 'a']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot launch independent native worker.');
        }
        $runningWorkers[] = $process;
        $job['process'] = $process;

        return $job;
    };
    $finish = static function(array $job) use ($wait): array {
        $wait(static fn(): bool => is_file($job['result']), 'independent worker completes');
        proc_close($job['process']);

        return json_decode((string)file_get_contents($job['result']), true, 32, JSON_THROW_ON_ERROR);
    };
    $ready = static function(array $job) use ($wait): int {
        $wait(static fn(): bool => is_file($job['ready']), 'independent native connection initialized');

        return (int)file_get_contents($job['ready']);
    };
    $blocked = static function(int $connectionId) use ($sql): bool {
        return 0 < (int)$sql->query("SELECT COUNT(*) C FROM performance_schema.data_lock_waits w INNER JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID={$connectionId}")->fetch_assoc()['C'];
    };
    $mutation = static fn(int $target, int $revision, string $signature): array => [
        'action' => 'mutate', 'actor' => $staff['organizer'], 'bearer' => $token('organizer'), 'institution' => $id,
        'key' => bin2hex(random_bytes(16)), 'revision' => $revision, 'signature' => $signature, 'target' => $target,
    ];
    $connection->startTransaction();
    $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id=1 FOR UPDATE')->fetch();
    try {
        $raceA = $spawn($mutation($staff['curator'], 4, 'a4'));
        $raceB = $spawn($mutation($staff['curator2'], 4, 'a4'));
        $idA = $ready($raceA);
        $idB = $ready($raceB);
        $wait(static fn(): bool => $blocked($idA) && $blocked($idB), 'both real C2 commands wait on AccessState lock');
    } finally {
        $connection->commitTransaction();
    }
    $resultA = $finish($raceA);
    $resultB = $finish($raceB);
    $statuses = [$resultA['status'], $resultB['status']];
    sort($statuses);
    $assert([200, 409] === $statuses, 'competing native assignments produce exactly one success and one conflict');
    $assert(5 === (int)$sql->query("SELECT UF_REVISION FROM b_hlbd_mf_institution WHERE ID={$internalId}")->fetch_assoc()['UF_REVISION']
        && '5' === $sql->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch_assoc()['assignments_revision'], 'racing assignments increment entity/state once');

    $currentCurator = (int)$sql->query("SELECT UF_USER_ID FROM b_hlbd_mf_institution_assignment WHERE UF_INSTITUTION_ID={$internalId} AND UF_ROLE='curator'")->fetch_assoc()['UF_USER_ID'];
    $currentLabel = $currentCurator === $staff['curator'] ? 'curator' : 'curator2';
    $otherCurator = $currentCurator === $staff['curator'] ? $staff['curator2'] : $staff['curator'];
    $release = $raceDirectory . '/login.release';
    $locked = $raceDirectory . '/login.locked';
    $login = $spawn(['action' => 'login', 'email' => $currentLabel . '@example.invalid', 'issuedToken' => 'C2RacingLogin', 'locked' => $locked, 'release' => $release]);
    $ready($login);
    $wait(static fn(): bool => is_file($locked), 'real LoginUseCase holds credential/identity lock before issuing token');
    $waitingMutation = $spawn($mutation($otherCurator, 5, 'a5'));
    $waitingId = $ready($waitingMutation);
    $wait(static fn(): bool => $blocked($waitingId), 'C2 replacement waits for real login identity lock');
    file_put_contents($release, 'release');
    $assert(200 === $finish($login)['status'] && 200 === $finish($waitingMutation)['status'], 'login commits before waiting assignment mutation');
    $assert(401 === $request('list', 'C2RacingLogin')[0], 'subsequent atomic assignment revokes the concurrently issued token');

    foreach (['active', 'password'] as $change) {
        $identity = 'active' === $change ? $staff['emptycurator'] : $staff['curator'];
        $email = 'active' === $change ? 'emptycurator@example.invalid' : 'curator@example.invalid';
        $connection->startTransaction();
        try {
            $identities->lockActive($identity);
            $waitingLogin = $spawn(['action' => 'login', 'email' => $email, 'issuedToken' => 'C2ShouldNotIssue' . $change]);
            $waitingId = $ready($waitingLogin);
            $wait(static fn(): bool => $blocked($waitingId), 'login waits while native identity ' . $change . ' changes');
            $writer = new CUser();
            $fields = 'active' === $change ? ['ACTIVE' => 'N'] : ['PASSWORD' => 'Changed-Native-Password-123', 'CONFIRM_PASSWORD' => 'Changed-Native-Password-123'];
            $assert($writer->Update($identity, $fields), 'native identity update succeeds under lock');
            $identities->revokeSessions($identity);
            $connection->commitTransaction();
        } catch (Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }
        $assert(401 === $finish($waitingLogin)['status'], 'current locked credentials reject login after ' . $change . ' change');
    }
    $connection->startTransaction();
    try {
        $identities->lockActive($staff['organizer']);
        $revokedActor = $spawn($mutation($otherCurator, 6, 'a6'));
        $waitingId = $ready($revokedActor);
        $wait(static fn(): bool => $blocked($waitingId), 'C2 actor authentication waits for identity lock');
        $identities->revokeSessions($staff['organizer']);
        $connection->commitTransaction();
    } catch (Throwable $exception) {
        $connection->rollbackTransaction();
        throw $exception;
    }
    $assert(401 === $finish($revokedActor)['status'], 'Bearer revoked during lock wait cannot authorize C2 write');
    $assert(6 === (int)$sql->query("SELECT UF_REVISION FROM b_hlbd_mf_institution WHERE ID={$internalId}")->fetch_assoc()['UF_REVISION'], 'actor revocation leaves institution version unchanged');
    $refresh('organizer');
    $beforeFailedLogin = $sql->query("SELECT UF_TOKEN,UF_TOKEN_EXPIRES_AT FROM b_uts_user WHERE VALUE_ID={$staff['organizer']}")->fetch_assoc();
    $sql->query("CREATE TRIGGER c2_fail_token BEFORE UPDATE ON b_uts_user FOR EACH ROW BEGIN IF NEW.UF_TOKEN='C2_FAIL_TOKEN' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture token write failure'; END IF; END");
    try {
        $failedLogin = $spawn(['action' => 'login', 'email' => 'organizer@example.invalid', 'issuedToken' => 'C2_FAIL_TOKEN']);
        $result = $finish($failedLogin);
        $assert(200 !== $result['status'] && str_ends_with($result['type'], 'RepositoryException'), 'native token persistence failure rejects login');
        $assert(0 === $result['transactionLevel'], 'failed native token write leaves no transaction or identity lock open');
        $assert($beforeFailedLogin === $sql->query("SELECT UF_TOKEN,UF_TOKEN_EXPIRES_AT FROM b_uts_user WHERE VALUE_ID={$staff['organizer']}")->fetch_assoc(), 'failed native token write preserves existing session through rollback');
    } finally {
        $sql->query('DROP TRIGGER c2_fail_token');
    }
    $recoveryLogin = $spawn(['action' => 'login', 'email' => 'organizer@example.invalid', 'issuedToken' => 'C2AfterFailure']);
    $recovered = $finish($recoveryLogin);
    $assert(200 === $recovered['status'] && 0 === $recovered['transactionLevel'] && 200 === $request('list', 'C2AfterFailure')[0], 'login recovers normally after native token storage fault');
    $sql->query("DELETE FROM b_uts_user WHERE VALUE_ID={$staff['nostaff']}");
    $legacyLogin = $spawn(['action' => 'login', 'email' => 'nostaff@example.invalid', 'issuedToken' => 'C2LegacyUts']);
    $legacyResult = $finish($legacyLogin);
    $assert(200 === $legacyResult['status'] && 0 === $legacyResult['transactionLevel'] && 403 === $request('list', 'C2LegacyUts')[0], 'legacy active Auth identity without UTS row can login through native upsert');
    $stage = 'constraints-and-protected-down';
    $rejectSql = static function(string $query, int $code, string $label) use ($sql, $assert): void {
        try {
            $result = $sql->query($query);
        } catch (mysqli_sql_exception $exception) {
            $assert($code === $exception->getCode(), $label);

            return;
        }
        $assert(false === $result && $code === $sql->errno, $label);
    };
    foreach (['b_hlbd_mf_institution_assignment' => ['ux_mf_assignment_slot', 'ix_mf_assignment_user'], 'b_hlbd_mf_access_change' => ['ux_mf_access_change_version', 'ix_mf_access_change_operation'], 'b_hlbd_mf_organization_change' => ['ux_mf_org_change_version', 'ix_mf_org_change_operation']] as $table => $indexes) {
        foreach ($indexes as $index) {
            $assert(0 < $sql->query("SHOW INDEX FROM {$table} WHERE Key_name='{$index}'")->num_rows, 'native lookup/uniqueness index exists: ' . $index);
        }
    }
    $head = $staff['head'];
    $rejectSql("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES({$internalId},'head',{$head},UTC_TIMESTAMP(),UTC_TIMESTAMP())", 1062, 'unique institution/role slot enforced');
    $rejectSql("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES(999999,'head',{$head},UTC_TIMESTAMP(),UTC_TIMESTAMP())", 1452, 'FK rejects unknown institution');
    $rejectSql("UPDATE b_hlbd_mf_institution_assignment SET UF_USER_ID=999999 WHERE UF_INSTITUTION_ID={$internalId} AND UF_ROLE='head'", 1452, 'FK rejects missing staff profile');
    $rejectSql("INSERT INTO b_hlbd_mf_institution_assignment(UF_INSTITUTION_ID,UF_ROLE,UF_USER_ID,UF_CREATED_AT,UF_UPDATED_AT) VALUES({$internalId},'teacher',{$head},UTC_TIMESTAMP(),UTC_TIMESTAMP())", 3819, 'DB rejects invalid institution role');
    $rejectSql("DELETE FROM b_hlbd_mf_institution WHERE ID={$internalId}", 1451, 'institution FK uses RESTRICT');
    $rejectSql("DELETE FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID={$head}", 1451, 'staff FK uses RESTRICT');
    $rejectSql('UPDATE b_hlbd_mf_organization_change SET UF_FROM_REVISION=-1 LIMIT 1', 3819, 'history rejects negative from revision');
    $rejectSql('UPDATE b_hlbd_mf_access_change SET UF_TO_REVISION=UF_FROM_REVISION LIMIT 1', 3819, 'history requires next revision');
    $rejectSql('INSERT INTO b_hlbd_mf_organization_change(UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT) SELECT UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT FROM b_hlbd_mf_organization_change LIMIT 1', 1062, 'history unique aggregate/version prevents duplicate change');
    $rejectSql("UPDATE b_hlbd_mf_access_change SET UF_DELTA='invalid-json' LIMIT 1", 3819, 'history delta must be valid JSON');
    $assert(1 === (int)$sql->query('SELECT COUNT(*) C FROM b_hlbd_mf_organization_change WHERE UF_FROM_REVISION=0 AND UF_TO_REVISION=1')->fetch_assoc()['C'], 'creation history allows revision zero');
    foreach ($tables as $keep) {
        $connection->startTransaction();
        try {
            foreach ($tables as $table) {
                if ($table !== $keep) {
                    $connection->queryExecute('DELETE FROM ' . $table);
                }
            }
            $expect(static fn() => $migration->down(), 'down independently protects ' . $keep);
            $assert($connection->isTableExists('mf_institution_operation'), 'rejected down does not partially delete schema');
        } finally {
            $connection->rollbackTransaction();
        }
    }
    echo json_encode(['status' => 'PASS', 'wave' => 'C2', 'checksPassed' => count($checks), 'checks' => $checks,
        'php' => PHP_VERSION, 'bitrix' => $fixture['kernelVersion'], 'mysql' => $sql->server_info,
        'httpSeam' => 'CLI subclass overrides only static HttpRequest::getInput; real request/filter/controller/serializer run.',
        'externalNetwork' => false, 'hostPorts' => false, 'commerceRequired' => false,
        'concurrency' => 'Independent PHP processes/native DB connections; actual lock waits observed through performance_schema.',
        'captchaSeam' => 'Offline no-op captcha port only in native LoginUseCase race workers; no external captcha requested.',
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $exception) {
    if (isset($raceDirectory)) {
        foreach (glob($raceDirectory . '/*.ready') as $readyFile) {
            fwrite(STDERR, 'Worker connection ' . basename($readyFile) . ': ' . file_get_contents($readyFile) . PHP_EOL);
        } foreach (glob($raceDirectory . '/*.result.json') as $resultFile) {
            fwrite(STDERR, 'Worker result ' . basename($resultFile) . ': ' . file_get_contents($resultFile) . PHP_EOL);
        } foreach (glob($raceDirectory . '/*.log') as $logFile) {
            fwrite(STDERR, 'Worker log ' . basename($logFile) . ': ' . substr((string)file_get_contents($logFile), -2000) . PHP_EOL);
        }
    }
    foreach ($runningWorkers as $worker) {
        if (is_resource($worker)) {
            proc_terminate($worker);
            proc_close($worker);
        }
    }
    fwrite(STDERR, json_encode(['status' => 'FAIL', 'stage' => $stage, 'checksPassed' => count($checks),
        'error' => $exception->getMessage(), 'cause' => $exception->getPrevious()?->getMessage(),
        'file' => basename($exception->getFile()), 'line' => $exception->getLine(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
