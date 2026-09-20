<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Bitrix\Main\Loader;

// Only called by the disposable Docker runner. Never loads the application's .env or database.
$fixture = require __DIR__ . '/../fixtures/w02/bootstrap.php';
$sql = $fixture['connection'];
ServiceLocator::getInstance()->registerByModuleSettings('main');
RegisterModuleDependences('main', 'OnUserTypeBuildList', 'main', CUserTypeDate::class, 'GetUserTypeDescription');
require_once '/kernel/modules/main/install/index.php';
(new main())->InstallTasks();
require_once '/kernel/modules/highloadblock/install/index.php';
if (!(new highloadblock())->InstallDB()) {
    throw new RuntimeException('Cannot install test highloadblock schema.');
}
foreach (['morefoto.access', 'morefoto.commerce', 'morefoto.organization', 'morefoto.media', 'rebit.notification', 'rebit.leadhunter'] as $module) {
    symlink('/app/public/local/modules/' . $module, $fixture['documentRoot'] . '/local/modules/' . $module);
}
ob_start();
try {
    foreach (['20260323120001', '20260326120008', '20260911120001', '20260911200001', '20260911210001', '20260911220001', '20260912210001', '20260912220001', '20260913010001', '20260913010002', '20260919090001', '20260919100001', '20260919130001'] as $id) {
        require_once '/app/public/local/php_interface/migrations.foundation/Version' . $id . '.php';
        $class = 'Sprint\Migration\Version' . $id;
        (new $class())->up();
    }
    require '/app/public/local/modules/morefoto.commerce/install/index.php';
    (new Morefoto_Commerce())->DoInstall();
    require '/app/public/local/modules/morefoto.media/install/index.php';
    (new Morefoto_Media())->DoInstall();
} finally {
    ob_end_clean();
}
foreach (['rebit.share', 'rebit.auth', 'morefoto.access', 'morefoto.commerce', 'morefoto.organization', 'morefoto.media'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Cannot load fixture module.');
    }
}
foreach (['organizer', 'another-organizer', 'teacher', 'unassigned', 'curator', 'head', 'another-teacher', 'c4-curator', 'c4-head'] as $name) {
    $writer = new CUser();
    $id = $writer->Add([
        'LOGIN' => $name . '@example.invalid', 'EMAIL' => $name . '@example.invalid',
        'NAME' => $name, 'PASSWORD' => 'A8-test-only-password!42',
        'CONFIRM_PASSWORD' => 'A8-test-only-password!42', 'ACTIVE' => 'Y',
    ]);
    if (false === $id || 0 >= (int)$id) {
        throw new RuntimeException('Cannot seed fixture identity.');
    }
    if ('organizer' === $name) {
        ServiceLocator::getInstance()->get(BootstrapOrganizerUseCase::class)->execute((int)$id);
    } elseif ('unassigned' !== $name) {
        $role = match ($name) {
            'another-organizer' => 'organizer',
            'curator', 'c4-curator' => 'curator',
            'head', 'c4-head' => 'head',
            default => 'teacher',
        };
        $statement = $sql->prepare('INSERT INTO b_hlbd_mf_staff_profile (UF_USER_ID, UF_ROLE, UF_ACTIVE, UF_REVISION, UF_ACCESS_REVISION, UF_CREATED_AT, UF_UPDATED_AT) VALUES (?, ?, 1, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
        $statement->bind_param('is', $id, $role);
        $statement->execute();
    }
}
$root = $fixture['documentRoot'];
$settings = require $root . '/local/.settings.php';
$settings['exception_handling']['value']['debug'] = false;
$settings['routing'] = ['value' => ['config' => ['rabit-api.php']]];
$settings['session'] = ['value' => ['mode' => 'default']];
file_put_contents($root . '/local/.settings.php', '<?php return ' . var_export($settings, true) . ';');
symlink('/app/public/local/routes', $root . '/local/routes');
symlink('/app/public/local/php_interface', $root . '/local/php_interface');
copy('/kernel/routing_index.php', $root . '/bitrix/routing_index.php');
copy('/app/public/.access.php', $root . '/.access.php');
file_put_contents($root . '/bitrix/php_interface/dbconn.php', <<<'PHP'
<?php
$DBType = 'mysql';
$DBHost = 'rabit-w02-mysql';
$DBLogin = 'root';
$DBPassword = '';
$DBName = 'rabit_w02';
define('BX_UTF', true);
define('NO_AGENT_CHECK', true);
define('NO_AGENT_STATISTIC', true);
define('NO_KEEP_STATISTIC', true);
PHP);
// The initialized root is shared with nginx and FPM, using the stock Bitrix routing entry point.
$copy = static function(string $source, string $target) use (&$copy): void {
    if (is_link($source)) {
        symlink(readlink($source), $target);
    } elseif (is_dir($source)) {
        mkdir($target, 0755);
        foreach (new DirectoryIterator($source) as $entry) {
            if (!$entry->isDot()) {
                $copy($entry->getPathname(), $target . '/' . $entry->getFilename());
            }
        }
    } else {
        copy($source, $target);
    }
};
$copy($root, '/runtime/public');
chmod('/runtime/public', 0755);
foreach (['bitrix', 'bitrix/php_interface', 'local', 'local/modules', 'upload'] as $directory) {
    chmod('/runtime/public/' . $directory, 0755);
}
foreach (['bitrix/cache', 'bitrix/managed_cache', 'bitrix/stack_cache', 'bitrix/tmp'] as $directory) {
    mkdir('/runtime/public/' . $directory, 0777, true);
    chmod('/runtime/public/' . $directory, 0777);
}
$runtimeUser = posix_getpwnam('www-data');
$runtimeUid = is_array($runtimeUser) ? (int)$runtimeUser['uid'] : 1000;
$runtimeGid = is_array($runtimeUser) ? (int)$runtimeUser['gid'] : 1000;
foreach ([
    ['/runtime/private', 0700],
    ['/runtime/private/media', 0700],
    ['/runtime/public/upload/morefoto/previews', 0755],
] as [$directory, $mode]) {
    if (!is_dir($directory) && !mkdir($directory, $mode, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create media runtime directory.');
    }
    if (!chown($directory, $runtimeUid) || !chgrp($directory, $runtimeGid) || !chmod($directory, $mode)) {
        throw new RuntimeException('Cannot secure media runtime directory.');
    }
}
echo "Disposable Auth/Access/Commerce/Organization/Media fixture ready; catalogue, institutions and photos are empty.\n";
