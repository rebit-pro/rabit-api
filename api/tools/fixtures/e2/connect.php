<?php

declare(strict_types=1);

use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\DI\ServiceLocator;

// Worker connects to the already-created disposable W02 fixture; never installs or changes its schema.
if (!isset($job['documentRoot']) || 1 !== preg_match('#^/tmp/rabit-w02-[a-f0-9]{16}$#D', $job['documentRoot'])) {
    throw new RuntimeException('Refusing unknown worker document root.');
}
$_SERVER['DOCUMENT_ROOT'] = $job['documentRoot'];
$_SERVER['SERVER_NAME'] = 'e2.invalid';
$_SERVER['HTTP_HOST'] = 'e2.invalid';
$_SERVER['REQUEST_URI'] = '/e2-fixture';
$_SERVER['SCRIPT_NAME'] = '/e2-fixture';
$_SERVER['REQUEST_METHOD'] = 'GET';
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
$application->initializeExtendedKernel(['get' => [], 'post' => [], 'files' => [], 'cookie' => [], 'server' => $_SERVER, 'env' => []]);
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
foreach (['rebit.share', 'rebit.auth', 'morefoto.access', 'morefoto.commerce'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Fixture module missing: ' . $module);
    }
}

ServiceLocator::getInstance()->registerByModuleSettings('main');
