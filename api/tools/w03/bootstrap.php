<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Context;
use Bitrix\Main\Context\Culture;
use Bitrix\Main\DB\MysqliConnection;
use Bitrix\Main\Data\ConnectionPool;
use Bitrix\Main\EventManager;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\Server;

/** Isolated real-kernel bootstrap. No project .env, prolog or production settings. */
$apiRoot = dirname(__DIR__, 2);
$kernelRoot = realpath(getenv('W03_BITRIX_ROOT') ?: '/kernel');
if (false === $kernelRoot || !is_file($apiRoot . '/vendor/autoload.php')) {
    throw new RuntimeException('Supply a Bitrix kernel and Composer dependencies.');
}
require_once $apiRoot . '/vendor/autoload.php';
define('BX_DIR_PERMISSIONS', 0770);
define('BX_FILE_PERMISSIONS', 0660);
spl_autoload_register(static function(string $class) use ($kernelRoot, $apiRoot): void {
    $roots = [
        'Bitrix\Main\\' => $kernelRoot . '/modules/main/lib/',
        'Sprint\Migration\\' => $apiRoot . '/public/local/modules/sprint.migration/lib/',
    ];
    foreach ($roots as $prefix => $root) {
        if (0 !== strncasecmp($class, $prefix, strlen($prefix))) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        foreach ([$relative, strtolower($relative)] as $path) {
            if (is_file($root . $path)) {
                require_once $root . $path;

                return;
            }
        }
    }
});
spl_autoload_register([Bitrix\Main\ORM\Loader::class, 'autoLoad']);
require_once $kernelRoot . '/modules/main/include/compatibility.php';
require_once $kernelRoot . '/modules/main/classes/general/sqlwhere.php';
require_once $kernelRoot . '/modules/main/tools.php';
require_once $kernelRoot . '/modules/main/classes/general/time.php';
CTimeZone::Disable(); // Fixture uses server time and no per-user timezone options.
spl_autoload_register([Loader::class, 'autoLoad']);
require_once $kernelRoot . '/modules/main/lib/localization/culture.php';

$runtimeRoot = getenv('W03_RUNTIME_ROOT') ?: '/tmp/rabit-w03-runtime';
if (!is_dir($runtimeRoot . '/bitrix/cache')) {
    mkdir($runtimeRoot . '/bitrix/cache', 0770, true);
}
$_SERVER['DOCUMENT_ROOT'] = $runtimeRoot;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/w03-fixture';
$configuration = Configuration::getInstance();
(new ReflectionProperty($configuration, 'isLoaded'))->setValue($configuration, true);
$configuration->add('cache', ['type' => 'files', 'root_directory' => $runtimeRoot, 'use_lock' => false]);
$configuration->add('cache_flags', ['rebit_share_uploaded_file_owner_max_ttl' => 0]);

// Skip only the global application constructor; real connection/ORM/cache run below.
$application = (new ReflectionClass(HttpApplication::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Application::class, 'instance'))->setValue(null, $application);
(new ReflectionProperty(Application::class, 'backgroundJobs'))
    ->setValue($application, new SplPriorityQueue())
;
$pool = new ConnectionPool();
$pool->setConnectionParameters('default', [
    'className' => MysqliConnection::class,
    'host' => 'rabit-w03-mysql',
    'database' => 'rabit_w03',
    'login' => 'root',
    'password' => '',
    'options' => 0,
    'include_after_connected' => '',
]);
$pool->useMasterOnly(true);
(new ReflectionProperty(Application::class, 'connectionPool'))->setValue($application, $pool);
$context = new Context($application);
$server = new Server($_SERVER);
$request = new class($server, [], [], [], []) extends HttpRequest {
    protected function prepareCookie(array $cookies): array
    {
        return $cookies;
    }
};
$context->initialize($request, new HttpResponse(), $server);
$context->setCulture(new Culture([
    'FORMAT_DATE' => 'YYYY-MM-DD',
    'FORMAT_DATETIME' => 'YYYY-MM-DD HH:MI:SS',
    'CHARSET' => 'UTF-8',
]));
$application->setContext($context);
// There are deliberately no installed-module event handlers in this fixture DB.
$events = EventManager::getInstance();
(new ReflectionProperty($events, 'isHandlersLoaded'))->setValue($events, true);
