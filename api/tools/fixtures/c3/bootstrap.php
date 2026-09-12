<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Sprint\Migration\Version20260913010001;
use Sprint\Migration\Version20260913010002;

// This is a disposable native fixture. It never loads the project's .env or production prolog.
$fixture = require __DIR__ . '/../w02/bootstrap.php';
ServiceLocator::getInstance()->registerByModuleSettings('main');
$connection = Application::getConnection();
$sql = $fixture['connection'];
$autoload = require '/app/vendor/autoload.php';
foreach (['Access' => 'access', 'Organization' => 'organization'] as $namespace => $suffix) {
    $autoload->addPsr4('Morefoto\\' . $namespace . '\\', '/app/public/local/modules/morefoto.' . $suffix . '/lib/', true);
    symlink('/app/public/local/modules/morefoto.' . $suffix, $fixture['documentRoot'] . '/local/modules/morefoto.' . $suffix);
}
RegisterModuleDependences('main', 'OnUserTypeBuildList', 'main', CUserTypeDate::class, 'GetUserTypeDescription');
require_once '/kernel/modules/highloadblock/install/index.php';
if (!(new highloadblock())->InstallDB()) {
    throw new RuntimeException('Native highloadblock installation failed.');
}
ob_start();
try {
    foreach (['Version20260323120001', 'Version20260326120008', 'Version20260911120001', 'Version20260911200001', 'Version20260911210001', 'Version20260912210001', 'Version20260913010001', 'Version20260913010002'] as $version) {
        require_once '/app/public/local/php_interface/migrations.foundation/' . $version . '.php';
        $class = 'Sprint\Migration\\' . $version;
        (new $class())->up();
    }
    // Repeated up is part of the native migration acceptance.
    (new Version20260913010001())->up();
    (new Version20260913010002())->up();
} finally {
    ob_end_clean();
}
if (!Loader::includeModule('morefoto.access') || !Loader::includeModule('morefoto.organization')) {
    throw new RuntimeException('C3 modules did not load.');
}
$locator = ServiceLocator::getInstance();
$users = new UserRepository();
$staff = [];
foreach (['organizer' => 'organizer', 'curator' => 'curator', 'head' => 'head', 'teacher' => 'teacher', 'teacher2' => 'teacher', 'inactiveteacher' => 'teacher', 'wrongrole' => 'curator', 'emptycurator' => 'curator', 'nostaff' => null] as $label => $role) {
    $writer = new CUser();
    $password = 'Disposable-C3-fixture-123!';
    $id = (int)$writer->Add(['LOGIN' => $label . '@c3.example.invalid', 'EMAIL' => $label . '@c3.example.invalid', 'NAME' => $label, 'PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password, 'ACTIVE' => 'Y']);
    if (1 > $id) {
        throw new RuntimeException('Cannot create native disposable identity.');
    }
    if (null !== $role) {
        $active = 'inactiveteacher' === $label ? 0 : 1;
        $sql->query("INSERT INTO b_hlbd_mf_staff_profile(UF_USER_ID,UF_ROLE,UF_ACTIVE,UF_REVISION,UF_ACCESS_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES({$id},'{$role}',{$active},1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    }
    $staff[$label] = $id;
    $users->updateToken($id, 'C3Token' . $id, DateTime::createFromTimestamp(time() + 3600));
}

return ['fixture' => $fixture, 'connection' => $connection, 'sql' => $sql, 'locator' => $locator, 'users' => $users, 'staff' => $staff, 'actorBearer' => 'C3Token' . $staff['organizer']];
