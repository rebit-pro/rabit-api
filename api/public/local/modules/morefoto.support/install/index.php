<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Support extends CModule
{
    public $MODULE_ID = 'morefoto.support';
    public $MODULE_NAME = 'MoreFoto — Support';
    public $MODULE_DESCRIPTION = 'Questions to the curator through the MAX group';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-25 15:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'rebit.notification', 'morefoto.access', 'morefoto.organization', 'morefoto.media'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        foreach (['mf_support_question', 'mf_support_message', 'mf_support_idempotency', 'mf_support_max_chat'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the K3 support migration first: missing ' . $table);
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
