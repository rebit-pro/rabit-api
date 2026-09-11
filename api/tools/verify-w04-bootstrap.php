<?php

declare(strict_types=1);

/**
 * Real kernel DI/router check with isolated global settings.
 * Usage: php verify-w04-bootstrap.php [api-root] [bitrix-root] [vendor-dir]
 * Does not install modules, run their business methods, connect to DB or send HTTP.
 */

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$temporary = null;

try {
    $check(80400 <= PHP_VERSION_ID, 'PHP 8.4 or newer is required.');
    $apiRoot = realpath($argv[1] ?? dirname(__DIR__));
    $kernelRoot = realpath($argv[2] ?? $apiRoot . '/public/bitrix');
    $vendorRoot = realpath($argv[3] ?? $apiRoot . '/vendor');
    $check(false !== $apiRoot && false !== $kernelRoot && false !== $vendorRoot, 'Required source root missing.');

    $temporary = sys_get_temp_dir() . '/rabit-w04-bootstrap-' . bin2hex(random_bytes(8));
    $check(mkdir($temporary . '/local', 0700, true), 'Cannot create isolated settings directory.');
    file_put_contents($temporary . '/local/.settings.php', '<?php return [];');
    $_SERVER['DOCUMENT_ROOT'] = $temporary;

    $autoload = require $vendorRoot . '/autoload.php';
    foreach (['Share', 'Auth', 'Notification', 'Leadhunter'] as $module) {
        $autoload->addPsr4(
            'Rebit\\' . $module . '\\',
            $apiRoot . '/public/local/modules/rebit.' . strtolower($module) . '/lib/',
            true,
        );
    }
    $autoload->addPsr4('Morefoto\Access\\', $apiRoot . '/public/local/modules/morefoto.access/lib/', true);
    require_once $kernelRoot . '/modules/main/lib/loader.php';
    Loader::registerNamespace('Bitrix\Main', $kernelRoot . '/modules/main/lib');
    spl_autoload_register([Loader::class, 'autoLoad']);

    // Actual Share bootstrap must install the custom builder before the core builder autoloads.
    $check(!class_exists('Bitrix\Main\Engine\ControllerBuilder', false), 'Kernel builder was loaded too early.');
    require $apiRoot . '/public/local/modules/rebit.share/include.php';
    $check(
        is_a('Bitrix\Main\Engine\ControllerBuilder', 'Rebit\Share\Infrastructure\Bitrix\ControllerBuilder', true),
        'Share controller builder alias is inactive.',
    );

    $locator = ServiceLocator::getInstance();
    $registered = [];
    foreach (['rebit.share', 'rebit.auth', 'rebit.notification', 'rebit.leadhunter', 'morefoto.access'] as $module) {
        $settings = require $apiRoot . '/public/local/modules/' . $module . '/.settings.php';
        foreach ($settings['services']['value'] ?? [] as $id => $definition) {
            $check(!isset($registered[$id]), 'Service registered by two providers: ' . $id);
            $check(
                !interface_exists($id)
                || !isset($definition['constructorParams'])
                || isset($definition['constructor']),
                'Interface-key with manual arguments must use constructor: ' . $id,
            );
            $locator->addInstanceLazy($id, $definition);
            $registered[$id] = $module;
        }
    }

    // These constructors are side-effect-free. Auth methods, controllers, queues and file storage are not executed.
    $targets = [
        'Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface',
        'Rebit\Share\Contracts\Access\AccessGuardInterface',
        'Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase',
        'Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase',
        'Morefoto\Access\Presentation\Console\BootstrapOrganizerCommand',
        'Rebit\Share\Application\Contract\Cache\CacheCleanerInterface',
        'Rebit\Share\Application\Contract\Auth\TokenResolverInterface',
        'Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface',
        'Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface',
        'Rebit\Auth\Application\Auth\UseCase\LoginUseCase',
        'Rebit\Auth\Application\Auth\UseCase\LogoutUseCase',
        'Rebit\Auth\Application\Auth\UseCase\RequestRegistrationCodeUseCase',
        'Rebit\Auth\Application\Auth\UseCase\ConfirmRegistrationUseCase',
    ];
    foreach ([
        'Rebit\Auth\Application\Auth\Contract\ClockInterface',
        'Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface',
        'Rebit\Share\Application\Contract\Auth\TokenRevokerInterface',
    ] as $id) {
        if (isset($registered[$id])) {
            $targets[] = $id;
        }
    }
    $resolved = [];
    foreach ($targets as $id) {
        $instance = $locator->get($id);
        $check($instance instanceof $id, 'Resolved instance does not implement requested type: ' . $id);
        $check($instance === $locator->get($id), 'Service is not a singleton: ' . $id);
        $resolved[$id] = $instance::class;
    }

    $settings = require $apiRoot . '/public/local/.settings_extra.php';
    $check(['rabit-api.php'] === $settings['routing']['value']['config'], 'Unexpected active route config.');
    $router = new Router();
    $configurator = new RoutingConfigurator();
    $configurator->setRouter($router);
    $configure = require $apiRoot . '/public/local/routes/rabit-api.php';
    $configure($configurator);
    $router->releaseRoutes();
    $routes = [];
    foreach ($router->getRoutes() as $route) {
        $route->compile();
        $controller = $route->getController();
        $check(isset($registered[$controller[0]]), 'Routed controller is not registered in module DI.');
        $check(method_exists($controller[0], $controller[1]), 'Route action does not exist.');
        $routes[] = $route->getUri();
    }
    $check(7 === count($routes), 'Active foundation + W06 route inventory changed.');
    $check(in_array('/api/v1/me', $routes, true), 'W06 profile route disappeared.');
    $check(in_array('/api/v1/lead', $routes, true), 'Lead route disappeared.');
    // Isolated disable rehearsal: only the unchanged Auth/Share route providers remain.
    $disabledRouter = new Router();
    $disabledConfigurator = new RoutingConfigurator();
    $disabledConfigurator->setRouter($disabledRouter);
    foreach (['rebit.auth', 'rebit.share'] as $module) {
        $provider = require $apiRoot . '/public/local/modules/' . $module . '/routes.php';
        $provider($disabledConfigurator);
    }
    $disabledRouter->releaseRoutes();
    $disabledPaths = [];
    foreach ($disabledRouter->getRoutes() as $route) {
        $route->compile();
        $disabledPaths[] = $route->getUri();
    }
    $check(5 === count($disabledPaths) && !in_array('/api/v1/lead', $disabledPaths, true), 'Disabled provider still exposes a route.');

    $migration = require $apiRoot . '/public/local/php_interface/migrations.cfg.php';
    $check(
        '/local/php_interface/migrations.foundation' === $migration['migration_dir']
        && false === $migration['migration_dir_absolute']
        && 'sprint_migration_versions' === $migration['migration_table'],
        'Active migration set/history changed.',
    );

    foreach ([
        'Version20260321120016.php', 'Version20260323120001.php', 'Version20260326120008.php',
        'Version20260326120009.php', 'Version20260713120001.php', 'Version20260715120001.php',
        'Version20260820120001.php',
    ] as $version) {
        $check(is_file($apiRoot . '/public/local/php_interface/migrations.foundation/' . $version), 'Original foundation migration disappeared: ' . $version);
    }

    echo json_encode([
        'status' => 'PASS',
        'phpVersion' => PHP_VERSION,
        'sourceRoot' => $apiRoot,
        'realKernelServiceLocator' => true,
        'shareBootstrapAlias' => true,
        'registeredServices' => count($registered),
        'resolvedSingletons' => $resolved,
        'compiledRoutes' => $routes,
        'disabledNotificationRouteFixture' => true,
        'migrationDirectory' => $migration['migration_dir'],
        'installedModulesOrMigrations' => false,
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
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(1);
} finally {
    if (null !== $temporary) {
        unlink($temporary . '/local/.settings.php');
        rmdir($temporary . '/local');
        rmdir($temporary);
    }
}
