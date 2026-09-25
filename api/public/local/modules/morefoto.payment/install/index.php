<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Payment extends CModule
{
    public $MODULE_ID = 'morefoto.payment';
    public $MODULE_NAME = 'MoreFoto — Payment';
    public $MODULE_DESCRIPTION = 'Payment attempts, provider reconciliation and confirmed payment facts';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-25 12:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'morefoto.access', 'morefoto.commerce'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        foreach (['mf_payment_attempt', 'mf_payment_fact', 'mf_payment_notification'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the G1 payment migration first: missing ' . $table);
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
