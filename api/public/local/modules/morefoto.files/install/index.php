<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Files extends CModule
{
    public $MODULE_ID = 'morefoto.files';
    public $MODULE_NAME = 'MoreFoto — Files';
    public $MODULE_DESCRIPTION = 'Delivery of purchased originals and ZIP archives by the order right';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-26 12:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'morefoto.commerce', 'morefoto.media'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        foreach (['mf_file_download', 'mf_file_download_request'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the J1 files migration first: missing ' . $table);
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
