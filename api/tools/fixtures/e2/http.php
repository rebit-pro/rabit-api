<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Server;
use Morefoto\Commerce\Application\Catalog\Service\AuthorizedCatalog;
use Morefoto\Commerce\Infrastructure\Adapter\CatalogTokenResolver;
use Morefoto\Commerce\Presentation\Controller\CatalogController;
use Morefoto\Commerce\Presentation\Mapper\CatalogResponseMapper;
use Morefoto\Commerce\Presentation\Request\CatalogRequestFactory;

// CLI transport seam only: all routing, parsing, filters, controllers, DI and persistence are real.
final class E2HttpRequest extends HttpRequest
{
    public static string $raw = '';

    public static function getInput(): string
    {
        return self::$raw;
    }
}

final class E2Http
{
    /** @param array<string, mixed> $query
     * @return array{status: int, body: array<string, mixed>, cacheControl: ?string, location: ?string}
     */
    public static function request(string $method, string $path, ?string $token = null, string $raw = '', ?string $key = null, array $query = [], ?AuthorizedCatalog $catalog = null, string $contentType = 'application/json'): array
    {
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
        if (null !== $key) {
            $values['HTTP_IDEMPOTENCY_KEY'] = $key;
        }
        E2HttpRequest::$raw = $raw;
        $server = new Server($values);
        $request = new E2HttpRequest($server, $query, [], [], []);
        Application::getInstance()->getContext()->initialize($request, new HttpResponse(), $server);
        $router = new Router();
        $configurator = new RoutingConfigurator();
        $configurator->setRouter($router);
        (require '/app/public/local/routes/rabit-api.php')($configurator);
        $router->releaseRoutes();
        $route = $router->match($request);
        if (null === $route) {
            return ['status' => 404, 'body' => [], 'cacheControl' => null, 'location' => null];
        }
        [$class, $methodName] = $route->getController();
        if (CatalogController::class !== $class) {
            throw new RuntimeException('Expected native Commerce route.');
        }
        $services = ServiceLocator::getInstance();
        // Fresh request controller as under FPM; its stateless dependencies come from native module DI.
        $controller = new CatalogController($catalog ?? $services->get(AuthorizedCatalog::class), $services->get(CatalogRequestFactory::class), $services->get(CatalogResponseMapper::class), $services->get(CatalogTokenResolver::class));
        $response = $controller->run(substr($methodName, 0, -6), [$route->getParametersValues()->toArray()]);
        if (!$response instanceof HttpResponse) {
            $response = new HttpResponse();
        }
        $controller->finalizeResponse($response);
        $status = (int)$response->getStatus();

        return ['status' => 0 === $status ? 200 : $status, 'body' => json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR), 'cacheControl' => $response->getHeaders()->get('Cache-Control'), 'location' => $response->getHeaders()->get('Location')];
    }
}
