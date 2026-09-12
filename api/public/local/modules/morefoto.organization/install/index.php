<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Organization extends CModule
{
    public $MODULE_ID = 'morefoto.organization';
    public $MODULE_NAME = 'MoreFoto — Organization';
    public $MODULE_DESCRIPTION = 'Institution records';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-11 21:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'highloadblock'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        if (!Application::getConnection()->isTableExists('b_hlbd_mf_institution')) {
            throw new RuntimeException('Apply the institution migration first.');
        }
        if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            ModuleManager::registerModule($this->MODULE_ID);
        }
    }

    public function DoUninstall(): void
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
