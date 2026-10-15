<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Production extends CModule
{
    public $MODULE_ID = 'morefoto.production';
    public $MODULE_NAME = 'MoreFoto — Production';
    public $MODULE_DESCRIPTION = 'Archive of paid prints for the lab and its private link';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-10-15 12:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.commerce', 'morefoto.media'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        foreach (['mf_production_schedule', 'mf_production_version', 'mf_production_line', 'mf_production_request'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the L1 production migration first: missing ' . $table);
            }
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
