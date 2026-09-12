<?php

declare(strict_types=1);

use Bitrix\Main\EventManager;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Entity;
use Bitrix\Main\DI\ServiceLocator;

// Second independent PHP process: native W02 CLI context, existing disposable schema only.
// $documentRoot is the root created by the main W02 fixture; no schema or production init runs.
if (!isset($documentRoot) || !is_string($documentRoot) || !str_starts_with($documentRoot, '/tmp/rabit-w02-') || !is_file($documentRoot . '/local/.settings.php')) {
    throw new RuntimeException('Invalid isolated fixture root.');
}
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
$autoload = require '/app/vendor/autoload.php';
$autoload->addPsr4('Morefoto\Access\\', '/app/public/local/modules/morefoto.access/lib/', true);
Loader::includeModule('morefoto.access');
Loader::includeModule('morefoto.organization');

ServiceLocator::getInstance()->registerByModuleSettings('main');
