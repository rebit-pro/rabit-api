<?php

declare(strict_types=1);
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

final class Morefoto_Handoff extends CModule
{
    public $MODULE_ID = 'morefoto.handoff';
    public $MODULE_NAME = 'MoreFoto — Handoff';
    public $MODULE_DESCRIPTION = 'Staff requests and handoff workflow';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-20 12:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        foreach (['rebit.share', 'morefoto.access', 'morefoto.organization', 'morefoto.media'] as $dependency) {
            if (!Loader::includeModule($dependency)) {
                throw new RuntimeException('Required module is unavailable: ' . $dependency);
            }
        }
        foreach (['mf_staff_request', 'mf_staff_request_row', 'mf_staff_request_history', 'mf_staff_request_idempotency'] as $table) {
            if (!Application::getConnection()->isTableExists($table)) {
                throw new RuntimeException('Apply the F1 handoff migration first: missing ' . $table);
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
