<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Server;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\ListVisibleInstitutionsUseCase;
use Morefoto\Organization\Application\Structure\UseCase\GetShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\ListShootsUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Infrastructure\Routing\StructureRouteParameters;
use Morefoto\Organization\Presentation\Controller\StructureController;
use Morefoto\Organization\Presentation\Controller\InstitutionController;
use Morefoto\Organization\Presentation\Request\InstitutionRequestFactory;
use Morefoto\Organization\Presentation\Request\StructureRequestFactory;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;

// CLI transport seam only: supplies php://input, without redefining native Bitrix classes.
final class C3NativeHttpRequest extends HttpRequest
{
    public static string $raw = '';

    public static function getInput(): string
    {
        return self::$raw;
    }
}

/**
 * Native routing/controller/filters/serializer/DB contract checks. This is not a browser E2E suite.
 *
 * @param array{
 *     connection: Connection,
 *     locator: ServiceLocator,
 *     assert: callable(bool, string): void,
 *     staff: array{organizer: int, curator: int, teacher: int},
 *     actorBearer: string,
 * } $context
 */
return static function(array $context): void {
    $assert = $context['assert'];
    $locator = $context['locator'];
    $connection = $context['connection'];
    $actor = $context['staff']['organizer'];
    $bearer = $context['actorBearer'];
    $key = static fn(): string => bin2hex(random_bytes(16));
    foreach ([HttpRequest::class, Router::class, Controller::class] as $class) {
        $path = (new ReflectionClass($class))->getFileName();
        $assert(is_string($path) && str_contains($path, '/modules/main/'), 'C3 HTTP: native kernel class origin ' . $class);
    }
    $institution = $locator->get(SaveInstitutionUseCase::class)->execute(
        $actor,
        $bearer,
        null,
        new InstitutionMutationInputDto($key(), 'HTTP contract fixture', '', null, false, null, false, null, null, false),
    );
    $parentPath = '/api/v1/institutions/' . $institution->id . '/shoots';
    $assert($locator->get(StructureController::class) instanceof StructureController, 'C3 HTTP: native module DI resolves the route controller');
    /** @param array<string,mixed> $query
     * @return array{status:int,body:array<string,mixed>,cache:?string,location:?string,route:array<string,mixed>}
     */
    $request = static function(string $method, string $path, ?string $token, string $raw = '', ?string $idempotencyKey = null, array $query = [], string $contentType = 'application/json') use ($locator): array {
        $values = $_SERVER;
        unset($values['HTTP_AUTHORIZATION'], $values['HTTP_IDEMPOTENCY_KEY']);
        $values['QUERY_STRING'] = http_build_query($query);
        $values['REQUEST_URI'] = $path . ([] === $query ? '' : '?' . $values['QUERY_STRING']);
        $values['REQUEST_METHOD'] = $method;
        $values['CONTENT_TYPE'] = $contentType;
        $values['HTTP_ACCEPT'] = 'application/json';
        if (null !== $token) {
            $values['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        if (null !== $idempotencyKey) {
            $values['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
        }
        C3NativeHttpRequest::$raw = $raw;
        $server = new Server($values);
        $http = new C3NativeHttpRequest($server, $query, [], [], []);
        $application = Application::getInstance();
        $application->getContext()->initialize($http, new HttpResponse(), $server);
        $router = new Router();
        $configurator = new RoutingConfigurator();
        $configurator->setRouter($router);
        (require '/app/public/local/routes/rabit-api.php')($configurator);
        $router->releaseRoutes();
        $route = $router->match($http);
        if (null === $route) {
            return ['status' => 404, 'body' => [], 'cache' => null, 'location' => null, 'route' => []];
        }
        $application->setCurrentRoute($route);
        // Match native routing_index.php: path parameters are also copied to GET before FPM runs the controller.
        $routedQuery = $query;
        foreach ($route->getParametersValues()->getValues() as $name => $value) {
            $routedQuery[$name] = $value;
        }
        $http = new C3NativeHttpRequest($server, $routedQuery, [], [], []);
        $application->getContext()->initialize($http, new HttpResponse(), $server);
        [$class, $methodName] = $route->getController();
        // As in FPM each request gets a fresh controller; application services come from actual module DI.
        $controller = match ($class) {
            InstitutionController::class => new InstitutionController(
                $locator->get(ListVisibleInstitutionsUseCase::class),
                $locator->get(SaveInstitutionUseCase::class),
                $locator->get(InstitutionRequestFactory::class),
                $locator->get(TokenResolverInterface::class),
            ),
            StructureController::class => new StructureController(
                $locator->get(ListShootsUseCase::class),
                $locator->get(GetShootUseCase::class),
                $locator->get(SaveShootUseCase::class),
                $locator->get(SaveGroupUseCase::class),
                $locator->get(StructureRequestFactory::class),
                $locator->get(StructureRouteParameters::class),
                $locator->get(TokenResolverInterface::class),
            ),
            default => throw new RuntimeException('Expected a real Organization route.'),
        };
        // HttpApplication supplies request dictionaries, not a hand-built array of path IDs.
        $response = $controller->run(substr($methodName, 0, -6), [$http->getPostList(), $http->getQueryList()]);
        if (!$response instanceof HttpResponse) {
            $response = new HttpResponse();
        }
        $controller->finalizeResponse($response);
        $status = (int)$response->getStatus();
        if (503 === $status) {
            $error = (new ReflectionProperty($controller, 'thrownException'))->getValue($controller);
            if ($error instanceof Throwable) {
                throw new RuntimeException('Unexpected native C3 HTTP service failure.', 0, $error);
            }
        }

        return [
            'status' => 0 === $status ? 200 : $status,
            'body' => json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR),
            'cache' => $response->getHeaders()->get('Cache-Control'),
            'location' => $response->getHeaders()->get('Location'),
            'route' => $route->getParametersValues()->toArray(),
        ];
    };
    $json = static fn(array $data): string => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $institutionPath = '/api/v1/institutions/' . $institution->id;
    $assert($locator->get(InstitutionController::class) instanceof InstitutionController, 'C2/C3 HTTP: native DI resolves the institution controller');
    $institutionPage = $request('GET', '/api/v1/institutions', $bearer, query: ['q' => 'HTTP contract fixture', 'page' => '1', 'pageSize' => '1']);
    $assert(200 === $institutionPage['status'] && 1 === $institutionPage['body']['meta']['total'] && $institution->id === $institutionPage['body']['data']['items'][0]['id'], 'C2/C3 HTTP: institution list reads original client pagination and search');
    $institutionPatch = $request('PATCH', $institutionPath, $bearer, $json(['name' => 'HTTP institution renamed', 'revision' => 1]), $key());
    $assert(200 === $institutionPatch['status'] && $institution->id === $institutionPatch['body']['data']['id'] && 2 === $institutionPatch['body']['data']['revision'], 'C2/C3 HTTP: native request binder accepts matched institution path parameters');
    foreach (['foreign-id', ['foreign-id']] as $spoofedId) {
        $institutionSpoof = $request('PATCH', $institutionPath, $bearer, $json(['name' => 'Query override', 'revision' => 2]), $key(), ['institution_id' => $spoofedId]);
        $assert(422 === $institutionSpoof['status'] && $institution->id === $institutionSpoof['route']['institution_id'], 'C2/C3 HTTP: client query cannot spoof institution ID or bypass unknown-field validation');
    }
    $institutionAfterSpoof = $request('GET', '/api/v1/institutions', $bearer, query: ['q' => 'HTTP institution renamed']);
    $assert(200 === $institutionAfterSpoof['status'] && 1 === $institutionAfterSpoof['body']['meta']['total'] && 2 === $institutionAfterSpoof['body']['data']['items'][0]['revision'], 'C2/C3 HTTP: rejected path query overrides leave institution revision unchanged');
    $unauthorized = $request('GET', $parentPath, null);
    $assert(401 === $unauthorized['status'], 'C3 HTTP: real Bearer prefilter rejects missing token');
    $assert(isset($unauthorized['body']['error']['code'], $unauthorized['body']['error']['message'], $unauthorized['body']['meta']['requestId']) && !isset($unauthorized['body']['data']) && 'no-store' === $unauthorized['cache'], 'C3 HTTP: error envelope and private cache headers are serialized');
    $assert(401 === $request('GET', $parentPath, 'invalid-token')['status'], 'C3 HTTP: real Bearer prefilter rejects invalid token');

    $shootKey = $key();
    $created = $request('POST', $parentPath, $bearer, $json(['name' => 'HTTP shoot', 'date' => '2026-10-01']), $shootKey);
    $assert(201 === $created['status'] && Uuid::isValid($created['body']['data']['id']) && 1 === $created['body']['data']['revision'], 'C3 HTTP: routed POST creates a persisted shoot with serialized UUID/revision');
    $shootPath = '/api/v1/shoots/' . $created['body']['data']['id'];
    $assert($shootPath === $created['location'] && 'no-store' === $created['cache'], 'C3 HTTP: create shoot Location and cache headers');
    $replayed = $request('POST', $parentPath, $bearer, $json(['name' => 'HTTP shoot', 'date' => '2026-10-01']), $shootKey);
    $assert(201 === $replayed['status'] && $created['body']['data'] === $replayed['body']['data'], 'C3 HTTP: replay returns the original successful resource');
    $assert(409 === $request('POST', $parentPath, $bearer, $json(['name' => 'Changed payload', 'date' => '2026-10-01']), $shootKey)['status'], 'C3 HTTP: conflicting idempotency payload returns 409');
    $page = $request('GET', $parentPath, $bearer, query: ['page' => '1', 'pageSize' => '1']);
    $assert(200 === $page['status'] && 1 === $page['body']['meta']['total'] && 1 === $page['body']['meta']['pageSize'] && '2026-10-01' === $page['body']['data']['items'][0]['date'], 'C3 HTTP: actual query pagination and ISO date serialization');
    $detail = $request('GET', $shootPath, $bearer);
    $assert(200 === $detail['status'] && [] === $detail['body']['data']['groups']['items'] && 0 === $detail['body']['data']['groups']['meta']['total'] && isset($detail['body']['data']['assignmentSignature']), 'C3 HTTP: editor exposes explicit groups page and assignment signature');
    $curator = 'C3Token' . $context['staff']['curator'];
    $assert(403 === $request('GET', $shootPath, $curator)['status'], 'C3 HTTP: editor remains organizer-only through native filters and UseCase');
    $spoof = Uuid::uuid4()->toString();
    $spoofed = $request('PATCH', $shootPath, $bearer, $json(['name' => 'Query override', 'revision' => 1]), $key(), ['shoot_id' => $spoof]);
    $assert(422 === $spoofed['status'] && $created['body']['data']['id'] === $spoofed['route']['shoot_id'], 'C3 HTTP: matched path remains authoritative and query ID overrides are rejected');
    $assert(422 === $request('GET', $parentPath, $bearer, query: ['institution_id' => $spoof])['status'], 'C3 HTTP: list query cannot override institution path');
    $assert(404 === $request('GET', '/api/v1/shoots/' . $spoof, $bearer)['status'], 'C3 HTTP: well-formed unknown shoot returns 404');
    $assert(422 === $request('GET', '/api/v1/shoots/not-a-uuid', $bearer)['status'], 'C3 HTTP: malformed route UUID returns 422');

    $renamed = $request('PATCH', $shootPath, $bearer, $json(['name' => 'HTTP renamed', 'revision' => 1]), $key());
    $preserved = $request('GET', $shootPath, $bearer);
    $assert(200 === $renamed['status'] && 2 === $renamed['body']['data']['revision'] && '2026-10-01' === $preserved['body']['data']['date'], 'C3 HTTP: omitted PATCH date preserves persisted date');
    $cleared = $request('PATCH', $shootPath, $bearer, $json(['date' => null, 'revision' => 2]), $key());
    $assert(200 === $cleared['status'] && null === $request('GET', $shootPath, $bearer)['body']['data']['date'], 'C3 HTTP: explicit null PATCH date clears persisted date');
    foreach (['1', 1.5, null, false] as $revision) {
        $assert(422 === $request('PATCH', $shootPath, $bearer, $json(['name' => 'invalid revision', 'revision' => $revision]), $key())['status'], 'C3 HTTP: revision type rejected ' . get_debug_type($revision));
    }
    foreach (['', '2026-02-29', '2026-10-01T00:00:00+03:00', 20261001, false] as $date) {
        $assert(422 === $request('POST', $parentPath, $bearer, $json(['name' => 'invalid date', 'date' => $date]), $key())['status'], 'C3 HTTP: invalid date rejected ' . json_encode($date, JSON_THROW_ON_ERROR));
    }
    $assert(400 === $request('POST', $parentPath, $bearer, '{', $key())['status'], 'C3 HTTP: malformed JSON returns 400');
    $assert(400 === $request('POST', $parentPath, $bearer, '[]', $key())['status'], 'C3 HTTP: JSON array body returns 400');
    $assert(400 === $request('POST', $parentPath, $bearer, '{}', $key(), contentType: 'text/plain')['status'], 'C3 HTTP: unsupported content type returns 400');
    $assert(422 === $request('POST', $parentPath, $bearer, $json(['name' => 'missing key']))['status'], 'C3 HTTP: mutation without idempotency key returns 422');

    $groupKey = $key();
    $groupBody = ['name' => 'HTTP group', 'groupKind' => 'regular', 'teacherId' => null, 'reason' => null];
    $group = $request('POST', $shootPath . '/groups', $bearer, $json($groupBody), $groupKey);
    $assert(201 === $group['status'] && 1 === $group['body']['data']['revision'], 'C3 HTTP: create group accepts explicit null reason');
    $groupPath = '/api/v1/groups/' . $group['body']['data']['id'];
    unset($groupBody['reason']);
    $sameGroup = $request('POST', $shootPath . '/groups', $bearer, $json($groupBody), $groupKey);
    $assert(201 === $sameGroup['status'] && $group['body']['data'] === $sameGroup['body']['data'], 'C3 HTTP: omitted reason and null reason have identical idempotency meaning');
    $groupRenamed = $request('PATCH', $groupPath, $bearer, $json(['name' => 'HTTP group renamed', 'revision' => 1, 'reason' => null]), $key());
    $assert(200 === $groupRenamed['status'] && 2 === $groupRenamed['body']['data']['revision'], 'C3 HTTP: ordinary group rename accepts explicit null reason');
    foreach (['sentAt', 'closesAt', 'deliveryDueAt', 'timezone', 'status', 'state', 'shootId', 'institutionId', 'groupKind'] as $field) {
        $assert(422 === $request('PATCH', $groupPath, $bearer, $json(['name' => 'Forbidden update', 'revision' => 2, $field => 'forbidden']), $key())['status'], 'C3 HTTP: ordinary group PATCH rejects owner field ' . $field);
    }
    foreach ([false, 1, [], (object)[]] as $reason) {
        $assert(422 === $request('PATCH', $groupPath, $bearer, $json(['name' => 'Invalid reason', 'revision' => 2, 'reason' => $reason]), $key())['status'], 'C3 HTTP: invalid reason type rejected ' . get_debug_type($reason));
    }
    $assert(422 === $request('GET', $shootPath, $bearer, query: ['pageSize' => '101'])['status'], 'C3 HTTP: editor groups page is bounded at 100');
    $calendar = $request('GET', $shootPath, $bearer)['body']['data']['groups']['items'][0];
    $assert('preparing' === $calendar['status'] && null === $calendar['sentAt'] && null === $calendar['closesAt'] && null === $calendar['deliveryDueAt'] && 'Europe/Moscow' === $calendar['timezone'], 'C3 HTTP: new group calendar is serialized without invented timestamps');
    $assigned = $request('PATCH', $groupPath, $bearer, $json(['revision' => 2, 'teacherId' => $context['staff']['teacher'], 'assignmentSignature' => $groupRenamed['body']['data']['assignmentSignature'], 'reason' => null]), $key());
    $assert(200 === $assigned['status'], 'C3 HTTP: first teacher assignment accepts null reason');
    $removedWithoutReason = $request('PATCH', $groupPath, $bearer, $json(['revision' => 3, 'teacherId' => null, 'assignmentSignature' => $assigned['body']['data']['assignmentSignature'], 'replaceAssignments' => true, 'reason' => null]), $key());
    $assert(422 === $removedWithoutReason['status'] && 'ASSIGNMENT_REASON_REQUIRED' === $removedWithoutReason['body']['error']['code'], 'C3 HTTP: null reason cannot bypass occupied assignment removal');
    $afterRejectedRemoval = $request('GET', $shootPath, $bearer)['body']['data']['groups']['items'][0];
    $assert(3 === $afterRejectedRemoval['revision'] && $context['staff']['teacher'] === $afterRejectedRemoval['teacherId'], 'C3 HTTP: rejected removal rolls back group revision and assignment');
    $removed = $request('PATCH', $groupPath, $bearer, $json(['revision' => 3, 'teacherId' => null, 'assignmentSignature' => $assigned['body']['data']['assignmentSignature'], 'replaceAssignments' => true, 'reason' => 'HTTP removal check']), $key());
    $assert(200 === $removed['status'] && 4 === $removed['body']['data']['revision'], 'C3 HTTP: explicit removal reason succeeds atomically');
    $assert(1 === (int)$connection->query("SELECT COUNT(*) AS N FROM b_hlbd_mf_shoot WHERE UF_PUBLIC_ID='" . $created['body']['data']['id'] . "'")->fetch()['N'], 'C3 HTTP: request validation and replays do not duplicate the shoot');
};
