<?php

declare(strict_types=1);

/**
 * W00 inventory only: no vendor, Bitrix bootstrap, controllers, HTTP or database.
 * Usage: php tools/verify-w00-router.php [api-root]
 * The optional root is useful when checking an isolated source snapshot.
 */

use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $check(80400 <= PHP_VERSION_ID, 'PHP 8.4 or newer is required.');
    $apiRoot = realpath($argv[1] ?? dirname(__DIR__));
    $check(false !== $apiRoot, 'API root does not exist.');
    $publicRoot = $apiRoot . '/public';

    $settingsFile = $publicRoot . '/local/php_interface/settings_extra.php';
    $settings = require $publicRoot . '/local/.settings_extra.php';
    $check(
        in_array(realpath($settingsFile), get_included_files(), true),
        'local/.settings_extra.php must load php_interface/settings_extra.php.',
    );
    $check(
        ['rabit-api.php'] === ($settings['routing']['value']['config'] ?? null),
        'Effective routing config must contain only rabit-api.php.',
    );

    foreach (['options', 'route', 'routingconfiguration', 'routingconfigurator', 'router'] as $file) {
        $kernelFile = $publicRoot . '/bitrix/modules/main/lib/routing/' . $file . '.php';
        $check(is_file($kernelFile), 'Missing Bitrix routing source: ' . $file . '.php');
        require_once $kernelFile;
    }

    $router = new Router();
    $configurator = new RoutingConfigurator();
    $configurator->setRouter($router);
    $configure = require $publicRoot . '/local/routes/rabit-api.php';
    $check($configure instanceof Closure, 'rabit-api.php must return a routing closure.');
    $configure($configurator);
    $router->releaseRoutes();

    /** @var array<string, array{0: string, 1: string}> $expectedRoutes */
    $expectedRoutes = [
        '/api/v1/auth/login' => [
            'Rebit\Auth\Presentation\Controller\AuthController',
            'loginAction',
        ],
        '/api/v1/auth/register/request-code' => [
            'Rebit\Auth\Presentation\Controller\AuthController',
            'requestRegistrationCodeAction',
        ],
        '/api/v1/auth/register/confirm' => [
            'Rebit\Auth\Presentation\Controller\AuthController',
            'confirmRegistrationAction',
        ],
        '/api/v1/auth/logout' => [
            'Rebit\Auth\Presentation\Controller\AuthController',
            'logoutAction',
        ],
        '/api/v1/share/file/upload/' => [
            'Rebit\Share\Presentation\Controller\FileController',
            'uploadAction',
        ],
        '/api/v1/lead' => [
            'Rebit\Notification\Presentation\Controller\LeadController',
            'submitAction',
        ],
        '/api/v1/lead/mos-dizel' => [
            'Rebit\Notification\Presentation\Controller\LeadController',
            'submitMosDizelAction',
        ],
    ];

    /** @var array<string, array{0: string, 1: string}> $actualRoutes */
    $actualRoutes = [];
    /** @var list<array{
     *     method: string,
     *     path: string,
     *     controller: string,
     *     action: string,
     * }> $routeReport */
    $routeReport = [];

    foreach ($router->getRoutes() as $route) {
        $path = $route->getUri();
        $check(!isset($actualRoutes[$path]), 'Duplicate route: ' . $path);
        $check(['POST'] === $route->getOptions()->getMethods(), 'Expected POST: ' . $path);
        $check(isset($expectedRoutes[$path]), 'Unexpected route: ' . $path);
        $check($expectedRoutes[$path] === $route->getController(), 'Wrong controller/action: ' . $path);
        $route->compile();

        $controller = $route->getController();
        $check(!class_exists($controller[0], false), 'Controller was loaded during inventory.');
        $actualRoutes[$path] = $controller;
        $routeReport[] = [
            'method' => 'POST',
            'path' => $path,
            'controller' => $controller[0],
            'action' => $controller[1],
        ];
    }

    $check(count($expectedRoutes) === count($actualRoutes), 'Missing expected routes.');

    $migrationConfig = require $publicRoot . '/local/php_interface/migrations.cfg.php';
    $check(
        '/local/php_interface/migrations.foundation' === ($migrationConfig['migration_dir'] ?? null)
        && false === ($migrationConfig['migration_dir_absolute'] ?? null)
        && 'sprint_migration_versions' === ($migrationConfig['migration_table'] ?? null),
        'Active migration config must use foundation and the existing history table.',
    );

    $expectedMigrations = [
        'Version20260321120016.php',
        'Version20260323120001.php',
        'Version20260326120008.php',
        'Version20260326120009.php',
        'Version20260713120001.php',
        'Version20260715120001.php',
        'Version20260820120001.php',
        'Version20260911120001.php',
        'Version20260911130001.php',
        'Version20260915110001.php',
    ];
    $migrationFiles = glob($publicRoot . $migrationConfig['migration_dir'] . '/*.php');
    $check(false !== $migrationFiles, 'Cannot enumerate foundation migrations.');
    $actualMigrations = array_map(
        static fn(string $path): string => basename($path),
        $migrationFiles,
    );
    sort($actualMigrations);
    $check($expectedMigrations === $actualMigrations, 'Unexpected foundation migration set; every new migration must be reviewed.');

    echo json_encode(
        [
            'status' => 'PASS',
            'phpVersion' => PHP_VERSION,
            'routingConfig' => ['rabit-api.php'],
            'routes' => $routeReport,
            'migrationConfig' => [
                'directory' => $migrationConfig['migration_dir'],
                'historyTable' => $migrationConfig['migration_table'],
            ],
            'migrations' => $actualMigrations,
            'controllersExecuted' => false,
            'migrationsExecuted' => false,
        ],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        json_encode(
            ['status' => 'FAIL', 'error' => $exception->getMessage()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL,
    );
    exit(1);
}
