<?php

declare(strict_types=1);

use Rebit\Share\Infrastructure\Bitrix\Module\ModuleRoutingTrait;
use Bitrix\Main\InvalidOperationException;
use Bitrix\Main\SystemException;

class Rebit_Share extends CModule
{
    use ModuleRoutingTrait;

    public $MODULE_ID = 'rebit.share';
    public $MODULE_VERSION = '1.0.0';
    public $MODULE_VERSION_DATE = '2026-03-20 08:00:00';
    public $MODULE_NAME = 'rebit.share - Общий модуль инфраструктуры';
    public $MODULE_DESCRIPTION = 'Модуль с общей для других модулей инфраструктурной составляющей';
    public $PARTNER_NAME = 'Rebit';
    public $PARTNER_URI = '';

    /**
     * @throws SystemException
     */
    public function __construct()
    {
        $this->initModuleRouting();
    }

    /**
     * @throws InvalidOperationException
     * @throws SystemException
     */
    public function DoInstall(): bool
    {
        RegisterModule($this->MODULE_ID);
        $this->installModuleRouting();

        return true;
    }

    /**
     * @throws SystemException
     * @throws InvalidOperationException
     */
    public function DoUninstall(): bool
    {
        UnRegisterModule($this->MODULE_ID);
        $this->uninstallModuleRouting();

        return true;
    }
}
