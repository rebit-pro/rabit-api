<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Server;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Type\DateTime;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\UseCase\GetInstitutionDetailUseCase;
use Morefoto\Organization\Application\Institution\UseCase\ListVisibleInstitutionsUseCase;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Structure\Repository\StructureRepository;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Request\InstitutionRequestFactory;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Bitrix\Main\Engine\Controller;
use Morefoto\Access\Application\Assignment\Service\InstitutionAccess;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $label);
    }
    $checks[] = $label;
};
try {
    // Same actual Bitrix kernel and migrations as C3, in this runner's fresh disposable MySQL only.
    $context = require __DIR__ . '/fixtures/c3/bootstrap.php';
    $connection = $context['connection'];
    $locator = $context['locator'];
    $staff = $context['staff'];
    $users = $context['users'];
    $actor = $staff['organizer'];
    $bearer = $context['actorBearer'];
    $key = static fn(): string => bin2hex(random_bytes(16));
    foreach ([HttpRequest::class, Router::class, Controller::class] as $class) {
        $assert(str_contains((string)(new ReflectionClass($class))->getFileName(), '/modules/main/'), 'Native Bitrix kernel origin: ' . $class);
    }
    $assert($locator->get(GetInstitutionDetailUseCase::class) instanceof GetInstitutionDetailUseCase, 'C4 use case resolves from actual module DI');
    $assert($locator->get(InstitutionController::class) instanceof InstitutionController, 'C4 route controller resolves from actual module DI');
    $assert(InstitutionAccess::class === $locator->get(InstitutionAccessInterface::class)::class, 'C4 uses actual institution access provider');

    /** Native routing_index.php + HttpApplication request dictionary boundary. No network/browser is claimed here.
     * @param array<string,mixed> $query
     *
     * @return array{status:int,body:array<string,mixed>,cache:?string,route:array<string,mixed>}
     */
    $request = static function(string $path, ?string $token, array $query = [], ?GetInstitutionDetailUseCase $override = null) use ($locator): array {
        $values = $_SERVER;
        unset($values['HTTP_AUTHORIZATION']);
        $values['REQUEST_METHOD'] = 'GET';
        $values['QUERY_STRING'] = http_build_query($query);
        $values['REQUEST_URI'] = $path . ([] === $query ? '' : '?' . $values['QUERY_STRING']);
        $values['HTTP_ACCEPT'] = 'application/json';
        if (null !== $token) {
            $values['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $server = new Server($values);
        $http = new HttpRequest($server, $query, [], [], []);
        $application = Application::getInstance();
        $application->getContext()->initialize($http, new HttpResponse(), $server);
        $router = new Router();
        $configurator = new RoutingConfigurator();
        $configurator->setRouter($router);
        (require '/app/public/local/routes/rabit-api.php')($configurator);
        $router->releaseRoutes();
        $route = $router->match($http);
        if (null === $route) {
            throw new RuntimeException('C4 route is not registered.');
        }
        $application->setCurrentRoute($route);
        foreach ($route->getParametersValues()->getValues() as $name => $value) {
            $query[$name] = $value;
        }
        $http = new HttpRequest($server, $query, [], [], []);
        $application->getContext()->initialize($http, new HttpResponse(), $server);
        [$class, $action] = $route->getController();
        if (InstitutionController::class !== $class || 'getAction' !== $action) {
            throw new RuntimeException('ORG-04 resolved an unexpected controller action.');
        }
        $controller = new InstitutionController(
            $locator->get(ListVisibleInstitutionsUseCase::class),
            $locator->get(SaveInstitutionUseCase::class),
            $locator->get(InstitutionRequestFactory::class),
            $locator->get(TokenResolverInterface::class),
            $override ?? $locator->get(GetInstitutionDetailUseCase::class),
        );
        $response = $controller->run('get', [$http->getPostList(), $http->getQueryList()]);
        if (!$response instanceof HttpResponse) {
            $response = new HttpResponse();
        }
        $controller->finalizeResponse($response);

        return [
            'status' => 0 === (int)$response->getStatus() ? 200 : (int)$response->getStatus(),
            'body' => json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR),
            'cache' => $response->getHeaders()->get('Cache-Control'),
            'route' => $route->getParametersValues()->toArray(),
        ];
    };
    $stage = 'fixtures';
    $institutions = $locator->get(SaveInstitutionUseCase::class);
    $shoots = $locator->get(SaveShootUseCase::class);
    $groups = $locator->get(SaveGroupUseCase::class);
    $access = $locator->get(InstitutionAccessInterface::class);
    $groupAccess = $locator->get(GroupAccessInterface::class);
    $institution = $institutions->execute($actor, $bearer, null, new InstitutionMutationInputDto($key(), 'C4 native institution', 'Real address', null, true, $staff['curator'], true, $staff['head'], $access->signature(), false));
    $foreign = $institutions->execute($actor, $bearer, null, new InstitutionMutationInputDto($key(), 'C4 foreign institution', 'Foreign address', null, false, null, false, null, null, false));
    $empty = $institutions->execute($actor, $bearer, null, new InstitutionMutationInputDto($key(), 'C4 empty institution', '', null, false, null, false, null, null, false));
    foreach (['curator', 'head'] as $role) {
        $users->updateToken($staff[$role], 'C3Token' . $staff[$role], DateTime::createFromTimestamp(time() + 3600));
    }
    $first = $shoots->execute($actor, $bearer, new StructureId($institution->id), true, new ShootMutationInputDto($key(), 'Same shoot name', true, '2028-02-29', null));
    $second = $shoots->execute($actor, $bearer, new StructureId($institution->id), true, new ShootMutationInputDto($key(), 'Same shoot name', false, null, null));
    $foreignShoot = $shoots->execute($actor, $bearer, new StructureId($foreign->id), true, new ShootMutationInputDto($key(), 'Foreign shoot', false, null, null));
    $firstGroup = $groups->execute($actor, $bearer, new StructureId($first->id), true, new GroupMutationInputDto($key(), 'Same group name', 'regular', null, true, $staff['teacher'], $groupAccess->signature(), false));
    $secondGroup = $groups->execute($actor, $bearer, new StructureId($second->id), true, new GroupMutationInputDto($key(), 'Same group name', 'staff', null, false, null, null, false));
    $foreignGroup = $groups->execute($actor, $bearer, new StructureId($foreignShoot->id), true, new GroupMutationInputDto($key(), 'Foreign group', 'regular', null, false, null, null, false));
    $users->updateToken($staff['teacher'], 'C3Token' . $staff['teacher'], DateTime::createFromTimestamp(time() + 3600));
    $path = '/api/v1/institutions/' . $institution->id;
    $stage = 'contract';
    $all = $request($path, $bearer);
    $assert(200 === $all['status'] && 'no-store' === $all['cache'], 'ORG-04 real routed response is successful and private');
    $data = $all['body']['data'];
    $assert($institution->id === $data['id'] && 'C4 native institution' === $data['name'] && 'Real address' === $data['address'] && 1 === $data['revision'], 'Institution fields and persisted revision are real');
    $assert($staff['curator'] === $data['curatorId'] && $staff['head'] === $data['headId'], 'Actual C2 curator/head assignment IDs are projected');
    $assert($access->signature() === $data['assignmentSignature'], 'Only actual organizer assignment signature is returned');
    $assert(['availability' => 'unavailable', 'reason' => 'dependenciesNotReady'] === $data['summary'], 'A6 summary is exact unavailable union without invented finance values');
    $assert(['page' => 1, 'pageSize' => 50, 'total' => 2, 'totalPages' => 1] === $data['shoots']['meta'], 'Shoots have default independent page metadata');
    $assert(['page' => 1, 'pageSize' => 50, 'total' => 2, 'totalPages' => 1] === $data['groups']['meta'], 'Groups have default independent page metadata');
    $assert([$second->id, $first->id] === array_column($data['shoots']['items'], 'id'), 'Shoot ordering uses newest timestamp and descending native ID tie-break');
    $assert([$secondGroup->id, $firstGroup->id] === array_column($data['groups']['items'], 'id'), 'Groups from all institution shoots have independent newest-first order');
    $assert($institution->id === $data['shoots']['items'][0]['institutionId'] && null === $data['shoots']['items'][0]['date'] && '2028-02-29' === $data['shoots']['items'][1]['date'], 'C3 UUID ancestry and nullable date contract preserved');
    $assert($second->id === $data['groups']['items'][0]['shootId'] && $first->id === $data['groups']['items'][1]['shootId'], 'Each group retains its actual shoot UUID');
    $assert('staff' === $data['groups']['items'][0]['groupKind'] && 'regular' === $data['groups']['items'][1]['groupKind'], 'C3 regular and staff group kinds preserved');
    $assert(null === $data['groups']['items'][0]['teacherId'] && $staff['teacher'] === $data['groups']['items'][1]['teacherId'], 'Group teacher assignment IDs come from actual access provider');
    $assert('preparing' === $data['groups']['items'][1]['status'] && 'Europe/Moscow' === $data['groups']['items'][1]['timezone'] && null === $data['groups']['items'][1]['sentAt'] && null === $data['groups']['items'][1]['closesAt'] && null === $data['groups']['items'][1]['deliveryDueAt'], 'C3 unsent group calendar has no invented times');
    $assert(!in_array($foreignShoot->id, array_column($data['shoots']['items'], 'id'), true) && !in_array($foreignGroup->id, array_column($data['groups']['items'], 'id'), true), 'Another institution never leaks into either child page');
    // Deliberate deterministic storage fixture: prove timestamps outrank IDs, then equal timestamps exercise the tie-break.
    foreach (['b_hlbd_mf_shoot' => $first->id, 'b_hlbd_mf_group' => $firstGroup->id] as $table => $uuid) {
        $connection->queryExecute("UPDATE {$table} SET UF_CREATED_AT='2026-01-01 00:00:00'");
        $connection->queryExecute("UPDATE {$table} SET UF_CREATED_AT='2026-02-01 00:00:00' WHERE UF_PUBLIC_ID='{$uuid}'");
    }
    $newest = $request($path, $bearer)['body']['data'];
    $assert($first->id === $newest['shoots']['items'][0]['id'] && $firstGroup->id === $newest['groups']['items'][0]['id'], 'createdAt descending takes priority over larger IDs on both pages');
    foreach (['b_hlbd_mf_shoot', 'b_hlbd_mf_group'] as $table) {
        $connection->queryExecute("UPDATE {$table} SET UF_CREATED_AT='2026-01-01 00:00:00'");
    }
    $stage = 'pagination';
    foreach ([[1, 1, $second->id, $secondGroup->id], [2, 1, $first->id, $secondGroup->id], [1, 2, $second->id, $firstGroup->id], [2, 2, $first->id, $firstGroup->id]] as [$shootPage, $groupPage, $shootId, $groupId]) {
        $page = $request($path, $bearer, ['shootsPage' => $shootPage, 'groupsPage' => $groupPage, 'pageSize' => 1])['body']['data'];
        $assert($shootId === $page['shoots']['items'][0]['id'] && $groupId === $page['groups']['items'][0]['id'], "Independent shoot/group pages {$shootPage}/{$groupPage}");
        $assert(['page' => $shootPage, 'pageSize' => 1, 'total' => 2, 'totalPages' => 2] === $page['shoots']['meta'] && ['page' => $groupPage, 'pageSize' => 1, 'total' => 2, 'totalPages' => 2] === $page['groups']['meta'], "Both total/page metadata preserved for {$shootPage}/{$groupPage}");
    }
    $beyond = $request($path, $bearer, ['shootsPage' => 99, 'groupsPage' => 98, 'pageSize' => 1])['body']['data'];
    $assert([] === $beyond['shoots']['items'] && [] === $beyond['groups']['items'] && 2 === $beyond['shoots']['meta']['total'] && 2 === $beyond['groups']['meta']['total'], 'Both out-of-range pages remain empty while preserving nonzero totals');
    $emptyData = $request('/api/v1/institutions/' . $empty->id, $bearer)['body']['data'];
    $assert([] === $emptyData['shoots']['items'] && [] === $emptyData['groups']['items'] && 0 === $emptyData['shoots']['meta']['total'] && 0 === $emptyData['groups']['meta']['total'], 'Genuinely empty institution returns real empty structural pages');
    $assert(null === $emptyData['curatorId'] && null === $emptyData['headId'] && $data['summary'] === $emptyData['summary'], 'Unassigned slots are null; empty structure does not pretend financial readiness');
    $assert(100 === $request($path, $bearer, ['pageSize' => 100])['body']['data']['groups']['meta']['pageSize'], 'Maximum allowed page size is accepted');
    $stage = 'scope';
    foreach (['curator', 'head'] as $role) {
        $token = 'C3Token' . $staff[$role];
        $own = $request($path, $token);
        $assert(200 === $own['status'] && $institution->id === $own['body']['data']['id'] && 2 === $own['body']['data']['groups']['meta']['total'], $role . ' reads complete structure only in actual own scope');
        $assert(!array_key_exists('assignmentSignature', $own['body']['data']), $role . ' never receives organizer assignment signature, even null');
        $assert(404 === $request('/api/v1/institutions/' . $foreign->id, $token)['status'], $role . ' cannot discover foreign institution');
    }
    $assert(404 === $request($path, 'C3Token' . $staff['emptycurator'])['status'], 'Unassigned curator cannot discover institution');
    $assert(403 === $request($path, 'C3Token' . $staff['teacher'])['status'], 'Assigned teacher cannot use ORG-04');
    $assert(403 === $request($path, 'C3Token' . $staff['teacher2'])['status'], 'Unassigned teacher cannot use ORG-04');
    foreach ([null, 'invalid-token'] as $token) {
        $error = $request($path, $token);
        $assert(401 === $error['status'] && !array_key_exists('data', $error['body']), 'Missing/invalid bearer is rejected before returning data');
        $assert(isset($error['body']['error']['code'], $error['body']['error']['message'], $error['body']['meta']['requestId']) && 'no-store' === $error['cache'], 'Authentication error uses private envelope and request ID');
    }
    $stage = 'validation';
    $assert(404 === $request('/api/v1/institutions/' . Uuid::uuid4()->toString(), $bearer)['status'], 'Unknown valid institution UUID returns 404');
    $assert(422 === $request('/api/v1/institutions/not-a-uuid', $bearer)['status'], 'Malformed institution UUID returns 422');
    foreach (['shootsPage', 'groupsPage', 'pageSize'] as $field) {
        foreach (['0', '-1', '1.5', '', '01', '1000001', ['1'], ['nested' => '1'], '1e1'] as $invalid) {
            $assert(422 === $request($path, $bearer, [$field => $invalid])['status'], 'Reject invalid ' . $field . ': ' . json_encode($invalid, JSON_THROW_ON_ERROR));
        }
    }
    $assert(422 === $request($path, $bearer, ['pageSize' => 101])['status'], 'Page size larger than 100 is rejected');
    foreach (['q', 'page', 'summary', 'curatorId', 'teacherId'] as $field) {
        $assert(422 === $request($path, $bearer, [$field => '1'])['status'], 'Unknown client query field rejected: ' . $field);
    }
    foreach ([$foreign->id, [$foreign->id]] as $spoof) {
        $error = $request($path, $bearer, ['institution_id' => $spoof]);
        $assert(422 === $error['status'] && $institution->id === $error['route']['institution_id'], 'Original URI validation prevents client query path spoofing');
    }
    $stage = 'provider failure';
    foreach (['b_hlbd_mf_shoot', 'b_hlbd_mf_group', 'b_hlbd_mf_institution_assignment', 'b_hlbd_mf_group_assignment'] as $table) {
        $connection->queryExecute("RENAME TABLE {$table} TO {$table}_c4_fault");
        try {
            $fault = $request($path, $bearer);
            $assert(503 === $fault['status'] && 'SERVICE_UNAVAILABLE' === $fault['body']['error']['code'] && !array_key_exists('data', $fault['body']), 'Actual missing provider table fails closed with 503: ' . $table);
            $assert('no-store' === $fault['cache'] && !str_contains(json_encode($fault['body'], JSON_THROW_ON_ERROR), $table), 'Provider failure does not expose internal SQL/storage names: ' . $table);
        } finally {
            $connection->queryExecute("RENAME TABLE {$table}_c4_fault TO {$table}");
        }
        $assert(200 === $request($path, $bearer)['status'], 'Actual provider recovers after fixture table restoration: ' . $table);
    }
    $stage = 'access revalidation';
    // A clock seam schedules a real DB access mutation between reads and final authorization, without fake repositories/providers.
    $withMutation = static function(Closure $mutation) use ($locator): GetInstitutionDetailUseCase {
        $clock = new class($mutation) implements CalendarClockInterface {
            public function __construct(private readonly Closure $mutation) {}

            public function now(): DateTimeImmutable
            {
                ($this->mutation)();

                return new DateTimeImmutable('now', new DateTimeZone('UTC'));
            }
        };

        return new GetInstitutionDetailUseCase(
            $locator->get(InstitutionRepository::class),
            $locator->get(StructureRepository::class),
            $locator->get(InstitutionAccessInterface::class),
            $locator->get(GroupAccessInterface::class),
            $locator->get(TokenResolverInterface::class),
            $clock,
        );
    };
    $revoked = $request($path, $bearer, override: $withMutation(static function() use ($users, $actor): void { $users->clearToken($actor); }));
    $assert(401 === $revoked['status'] && !array_key_exists('data', $revoked['body']), 'Bearer revoked during actual structure read never returns the partially prepared data');
    $users->updateToken($actor, $bearer, DateTime::createFromTimestamp(time() + 3600));
    $changed = $request($path, $bearer, override: $withMutation(static function() use ($connection, $actor): void {
        $connection->queryExecute("UPDATE b_hlbd_mf_staff_profile SET UF_ACCESS_REVISION=UF_ACCESS_REVISION+1 WHERE UF_USER_ID={$actor}");
    }));
    $assert(409 === $changed['status'] && 'ACCESS_CHANGED' === $changed['body']['error']['code'] && !array_key_exists('data', $changed['body']), 'Access revision changed during read is rejected before serialization');
    $assert(200 === $request($path, $bearer)['status'], 'Following fresh read uses the current access revision');
    echo json_encode(['status' => 'passed', 'checks' => count($checks), 'details' => $checks, 'runtime' => ['php' => PHP_VERSION, 'bitrix' => $context['fixture']['kernelVersion'], 'mysql' => $context['sql']->server_info]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
    for ($previous = $error->getPrevious(); null !== $previous; $previous = $previous->getPrevious()) {
        fwrite(STDERR, 'Caused by ' . $previous::class . ': ' . $previous->getMessage() . PHP_EOL);
    }
    echo json_encode(['status' => 'failed', 'stage' => $stage, 'checks' => count($checks), 'details' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(1);
}
