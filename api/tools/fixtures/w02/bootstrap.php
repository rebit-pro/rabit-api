<?php

declare(strict_types=1);
use Bitrix\Main\DB\MysqliConnection;
use Bitrix\Main\EventManager;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Entity;

/**
 * Real kernel/ORM/CUser with an isolated document root and installation schema.
 * Bootstrap seam: CLI reconstructs main's global context; full prolog, agents,
 * application init.php, mail, licensed configuration and production data are not loaded.
 * No Bitrix classes or persistence methods are replaced.
 *
 * @return array{documentRoot: string, connection: mysqli, kernelVersion: string}
 */
return (static function(): array {
    $database = 'rabit_w02';
    $connection = new mysqli('rabit-w02-mysql', 'root', '', $database);
    $connection->set_charset('utf8mb4');
    $tables = $connection->query('SHOW TABLES');
    if (0 !== $tables->num_rows) {
        throw new RuntimeException('W02 fixture database must be empty; refusing to change existing data.');
    }
    $schema = file_get_contents('/kernel/modules/main/install/mysql/install.sql');
    if (false === $schema) {
        throw new RuntimeException('Cannot read licensed main installation schema.');
    }
    $connection->multi_query($schema);
    do {
        $result = $connection->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());
    $connection->query("INSERT INTO b_module (ID) VALUES ('main'), ('rebit.share'), ('rebit.auth'), ('sprint.migration')");
    $connection->query("INSERT INTO b_culture (ID, CODE, NAME, FORMAT_DATE, FORMAT_DATETIME, FORMAT_NAME, CHARSET) VALUES (1, 'en', 'W02 fixture', 'DD.MM.YYYY', 'DD.MM.YYYY HH:MI:SS', '#NAME# #LAST_NAME#', 'UTF-8')");
    $connection->query("INSERT INTO b_language (LID, DEF, NAME, CULTURE_ID) VALUES ('en', 'Y', 'W02 fixture', 1)");
    $connection->query("INSERT INTO b_lang (LID, DEF, NAME, DIR, LANGUAGE_ID, CULTURE_ID) VALUES ('s1', 'Y', 'W02 fixture', '/', 'en', 1)");
    $connection->query("INSERT INTO b_group (ID, ACTIVE, C_SORT, NAME, ANONYMOUS) VALUES (1, 'Y', 1, 'Administrators', 'N'), (2, 'Y', 2, 'Everyone', 'Y')");
    foreach (['check_agents', 'check_events', 'event_log_register', 'event_log_user_edit', 'event_log_user_delete'] as $option) {
        $connection->query("INSERT INTO b_option (MODULE_ID, NAME, VALUE) VALUES ('main', '{$option}', 'N')");
    }

    $documentRoot = sys_get_temp_dir() . '/rabit-w02-' . bin2hex(random_bytes(8));
    foreach (['/bitrix/php_interface', '/local/modules', '/upload'] as $directory) {
        if (!mkdir($documentRoot . $directory, 0700, true)) {
            throw new RuntimeException('Cannot create isolated Bitrix document root.');
        }
    }
    symlink('/kernel/modules', $documentRoot . '/bitrix/modules');
    symlink('/app/public/local/modules/sprint.migration', $documentRoot . '/local/modules/sprint.migration');
    symlink('/app/public/local/modules/rebit.auth', $documentRoot . '/local/modules/rebit.auth');
    symlink('/app/public/local/modules/rebit.share', $documentRoot . '/local/modules/rebit.share');
    $settings = [
        'connections' => ['value' => ['default' => [
            'className' => MysqliConnection::class,
            'host' => 'rabit-w02-mysql', 'database' => $database, 'login' => 'root', 'password' => '',
            'options' => 0,
        ]]],
        'cache' => ['value' => ['type' => 'none']],
        'exception_handling' => ['value' => ['debug' => true, 'handled_errors_types' => E_ERROR | E_PARSE]],
        'utf_mode' => ['value' => true],
        'default_charset' => ['value' => 'UTF-8'],
    ];
    file_put_contents($documentRoot . '/local/.settings.php', '<?php return ' . var_export($settings, true) . ';');
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
    $_SERVER['SERVER_NAME'] = 'w02.invalid';
    $_SERVER['HTTP_HOST'] = 'w02.invalid';
    $_SERVER['REQUEST_URI'] = '/w02-fixture';
    $_SERVER['SCRIPT_NAME'] = '/w02-fixture';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = '80';
    foreach ([
        'B_PROLOG_INCLUDED' => true, 'SITE_ID' => 's1', 'LANG' => 's1', 'LANGUAGE_ID' => 'en',
        'LANG_ADMIN_LID' => 'en', 'SITE_CHARSET' => 'UTF-8', 'LANG_CHARSET' => 'UTF-8',
        'FORMAT_DATE' => 'DD.MM.YYYY', 'FORMAT_DATETIME' => 'DD.MM.YYYY HH:MI:SS',
        'SITE_DIR' => '/', 'LANG_DIR' => '/', 'BX_SKIP_SESSION_EXPAND' => true,
        'NOT_CHECK_PERMISSIONS' => true, 'BX_NO_ACCELERATOR_RESET' => true,
        'NO_KEEP_STATISTIC' => true, 'NO_AGENT_CHECK' => true,
    ] as $name => $value) {
        define($name, $value);
    }
    require_once '/kernel/modules/main/bx_root.php';
    require_once '/kernel/modules/main/lib/loader.php';
    require_once '/kernel/modules/main/include/autoload.php';
    require_once '/kernel/modules/main/classes/general/version.php';
    require_once '/kernel/modules/main/tools.php';
    require_once '/kernel/modules/main/filter_tools.php';
    require_once '/kernel/modules/main/include/constants.php';
    require_once '/app/vendor/autoload.php';

    $application = HttpApplication::getInstance();
    $application->initializeExtendedKernel([
        'get' => [], 'post' => [], 'files' => [], 'cookie' => [], 'server' => $_SERVER, 'env' => [],
    ]);
    CAllDatabase::registerAutoload();
    $GLOBALS['DB'] = new CDatabase();
    $GLOBALS['DBType'] = 'mysql';
    $GLOBALS['CACHE_MANAGER'] = new CCacheManager();
    $GLOBALS['stackCacheManager'] = new CStackCacheManager();
    $application->getContext()->initializeCulture('s1', 'en');
    $GLOBALS['USER_FIELD_MANAGER'] = new CUserTypeManager();
    $GLOBALS['APPLICATION'] = new CMain();
    $GLOBALS['USER'] = new CUser();
    $GLOBALS['MESS'] = [];
    $GLOBALS['ALL_LANG_FILES'] = [];
    IncludeModuleLangFile('/kernel/modules/main/tools.php');
    // Relevant native registrations from main/install/index.php (not class stubs).
    foreach ([CUserTypeString::class, CUserTypeInteger::class, CUserTypeDateTime::class, CUserTypeBoolean::class] as $type) {
        RegisterModuleDependences('main', 'OnUserTypeBuildList', 'main', $type, 'GetUserTypeDescription');
    }
    foreach (['OnAfterUserTypeAdd', 'OnAfterUserTypeUpdate', 'OnAfterUserTypeDelete'] as $event) {
        EventManager::getInstance()->registerEventHandler('main', $event, 'main', Entity::class, 'onUserTypeChange');
    }
    Loader::registerNamespace('Sprint\Migration', '/app/public/local/modules/sprint.migration/lib');
    require_once '/app/public/local/modules/sprint.migration/include.php';

    return [
        'documentRoot' => $documentRoot,
        'connection' => $connection,
        'kernelVersion' => defined('SM_VERSION') ? SM_VERSION : 'unknown',
    ];
})();
