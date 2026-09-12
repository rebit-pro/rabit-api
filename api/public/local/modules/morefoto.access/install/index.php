<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Access extends CModule
{
    public $MODULE_ID = 'morefoto.access';
    public $MODULE_NAME = 'MoreFoto — Access';
    public $MODULE_DESCRIPTION = 'Staff profiles and authorization';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-11 20:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): bool
    {
        foreach (['highloadblock', 'rebit.share', 'rebit.auth'] as $module) {
            if (!Loader::includeModule($module)) {
                throw new RuntimeException('Required module is unavailable: ' . $module);
            }
        }
        foreach (['b_hlbd_mf_staff_profile', 'mf_access_state'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the W06 Access migration before installation.');
            }
        }
        if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            ModuleManager::registerModule($this->MODULE_ID);
        }

        return true;
    }

    public function DoUninstall(): bool
    {
        // Preserve profile data. Schema rollback is a separate, guarded migration operation.
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }
}
