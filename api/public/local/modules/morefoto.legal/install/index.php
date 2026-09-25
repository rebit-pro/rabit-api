<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Legal extends CModule
{
    public $MODULE_ID = 'morefoto.legal';
    public $MODULE_NAME = 'MoreFoto — Legal';
    public $MODULE_DESCRIPTION = 'Legal documents, seller requisites and the consent journal';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-25 23:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        if (!Loader::includeModule('rebit.share')) {
            throw new RuntimeException('Required module is unavailable: rebit.share');
        }
        if (!Application::getConnection()->isTableExists('mf_legal_consent')) {
            throw new RuntimeException('Apply the legal consent migration first: missing mf_legal_consent');
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
