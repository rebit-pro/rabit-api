<?php

declare(strict_types=1);

use Bitrix\Main\ModuleManager;

final class Morefoto_Commerce extends CModule
{
    public $MODULE_ID = 'morefoto.commerce';
    public $MODULE_NAME = 'MoreFoto — каталог';
    public $MODULE_DESCRIPTION = 'Внутренние сценарии каталога товаров';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-09-11 22:00:00';
    public $PARTNER_NAME = 'ReBit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): void
    {
        if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            RegisterModule($this->MODULE_ID);
        }
    }

    public function DoUninstall(): void
    {
        // Uninstalling application code must preserve catalogue data.
        UnRegisterModule($this->MODULE_ID);
    }
}
