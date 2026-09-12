<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;

// Only called by the disposable Docker runner. Never loads the application's .env or database.
$fixture = require __DIR__ . '/../fixtures/w02/bootstrap.php';
$sql = $fixture['connection'];
ServiceLocator::getInstance()->registerByModuleSettings('main');
require_once '/kernel/modules/main/install/index.php';
(new main())->InstallTasks();
require_once '/kernel/modules/highloadblock/install/index.php';
if (!(new highloadblock())->InstallDB()) {
    throw new RuntimeException('Cannot install test highloadblock schema.');
}
foreach (['morefoto.access', 'morefoto.commerce', 'morefoto.organization', 'rebit.notification', 'rebit.leadhunter'] as $module) {
    symlink('/app/public/local/modules/' . $module, $fixture['documentRoot'] . '/local/modules/' . $module);
}
ob_start();
try {
    foreach (['20260323120001', '20260326120008', '20260911120001', '20260911200001', '20260911220001', '20260912220001'] as $id) {
        require_once '/app/public/local/php_interface/migrations.foundation/Version' . $id . '.php';
        $class = 'Sprint\\Migration\\Version' . $id;
        (new $class())->up();
    }
    require '/app/public/local/modules/morefoto.commerce/install/index.php';
    (new Morefoto_Commerce())->DoInstall();
} finally {
    ob_end_clean();
}
foreach (['rebit.share', 'rebit.auth', 'morefoto.access', 'morefoto.commerce'] as $module) {
    if (!\Bitrix\Main\Loader::includeModule($module)) {
        throw new RuntimeException('Cannot load fixture module.');
    }
}
foreach (['organizer', 'another-organizer', 'teacher', 'unassigned'] as $name) {
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
        $role = 'another-organizer' === $name ? 'organizer' : 'teacher';
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
echo "Disposable Auth/Access/Commerce fixture ready; catalogue is empty.\n";
