<?php

declare(strict_types=1);

/**
 * Проверка двух маршрутов rebit.notification на реальном ядре Bitrix.
 *
 * Не создаёт контроллеры, не обращается к БД/сети и не отправляет сообщения.
 * Usage: php tools/verify-mos-dizel-lead-route.php [api-root] [bitrix-root] [vendor-dir]
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;
use Rebit\Notification\Application\Lead\Port\MosDizelLeadNotifierInterface;
use Rebit\Notification\Application\Lead\UseCase\SubmitMosDizelLeadUseCase;
use Rebit\Notification\Presentation\Controller\LeadController;
use Rebit\Notification\Presentation\Controller\MosDizelLeadController;

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $check(80400 <= PHP_VERSION_ID, 'PHP 8.4 or newer is required.');

    $apiRoot = realpath($argv[1] ?? dirname(__DIR__));
    $kernelRoot = realpath($argv[2] ?? $apiRoot . '/public/bitrix');
    $vendorRoot = realpath($argv[3] ?? $apiRoot . '/vendor');
    $check(false !== $apiRoot && false !== $kernelRoot && false !== $vendorRoot, 'Required source root missing.');

    $autoload = require $vendorRoot . '/autoload.php';
    $autoload->addPsr4(
        'Rebit\Notification\\',
        $apiRoot . '/public/local/modules/rebit.notification/lib/',
        true,
    );
    $autoload->addPsr4(
        'Rebit\Share\\',
        $apiRoot . '/public/local/modules/rebit.share/lib/',
        true,
    );

    require_once $kernelRoot . '/modules/main/lib/loader.php';
    Loader::registerNamespace('Bitrix\Main', $kernelRoot . '/modules/main/lib');
    spl_autoload_register([Loader::class, 'autoLoad']);

    foreach (['options', 'route', 'routingconfiguration', 'routingconfigurator', 'router'] as $file) {
        require_once $kernelRoot . '/modules/main/lib/routing/' . $file . '.php';
    }

    $router = new Router();
    $configurator = new RoutingConfigurator();
    $configurator->setRouter($router);

    $configure = require $apiRoot . '/public/local/modules/rebit.notification/routes.php';
    $configure($configurator);
    $router->releaseRoutes();

    $expected = [
        '/api/v1/lead' => [LeadController::class, 'submitAction'],
        '/api/v1/lead/mos-dizel' => [MosDizelLeadController::class, 'submitAction'],
    ];
    $actual = [];

    foreach ($router->getRoutes() as $route) {
        $route->compile();
        $path = $route->getUri();
        $controller = $route->getController();

        $check(['POST'] === $route->getOptions()->getMethods(), 'Expected POST: ' . $path);
        $check(isset($expected[$path]) && $expected[$path] === $controller, 'Unexpected controller action: ' . $path);
        $check(method_exists($controller[0], $controller[1]), 'Route action does not exist: ' . $path);

        $actual[$path] = $controller;
    }

    $check($expected === $actual, 'Notification route inventory changed.');

    $settings = require $apiRoot . '/public/local/modules/rebit.notification/.settings.php';
    $services = $settings['services']['value'];
    $controllerDefinition = $services[MosDizelLeadController::class] ?? null;
    $useCaseDefinition = $services[SubmitMosDizelLeadUseCase::class] ?? null;
    $notifierDefinition = $services[MosDizelLeadNotifierInterface::class] ?? null;
    $check(is_array($controllerDefinition), 'MosDizelLeadController is absent from module DI.');
    $check(is_callable($controllerDefinition['constructorParams'] ?? null), 'MosDizelLeadController DI is missing.');
    $check(is_array($useCaseDefinition), 'SubmitMosDizelLeadUseCase is absent from module DI.');
    $check(is_callable($useCaseDefinition['constructorParams'] ?? null), 'SubmitMosDizelLeadUseCase DI is missing.');
    $check(is_array($notifierDefinition), 'MosDizelLeadNotifierInterface is absent from module DI.');
    $check(is_callable($notifierDefinition['constructor'] ?? null), 'MosDizelLeadNotifierInterface DI is missing.');

    $migration = $apiRoot . '/public/local/php_interface/migrations.foundation/Version20260915110001.php';
    $check(is_file($migration), 'Mos-dizel mail-event migration is missing.');

    echo json_encode([
        'status' => 'PASS',
        'routes' => $actual,
        'dedicatedEmailDi' => true,
        'migration' => basename($migration),
        'controllersExecuted' => false,
        'businessMethodsExecuted' => false,
        'databaseUsed' => false,
        'networkUsed' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL',
        'error' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    exit(1);
}
