<?php

declare(strict_types=1);

/**
 * Модуль мониторинга заявок с внешних площадок (fl.ru и другие)
 */
class Rebit_Leadhunter extends CModule
{
    public $MODULE_ID = 'rebit.leadhunter';
    public $MODULE_NAME = 'rebit.leadhunter — Охота за лидами';
    public $MODULE_DESCRIPTION = 'Мониторинг заявок с внешних площадок по ключевым словам и разделам с доставкой в Telegram';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-07-13 12:00:00';
    public $PARTNER_NAME = 'rebit';
    public $PARTNER_URI = 'https://rebit-pro.ru';

    public function DoInstall(): bool
    {
        RegisterModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall(): bool
    {
        UnRegisterModule($this->MODULE_ID);

        return true;
    }
}
