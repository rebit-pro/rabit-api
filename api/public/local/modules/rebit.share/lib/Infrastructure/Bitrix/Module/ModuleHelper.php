<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Bitrix\Module;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

final class ModuleHelper
{
    /**
     * Проверяет, установлен ли модуль, и выбрасывает исключение если нет.
     * В админке исключения не выбрасываются, чтобы была возможность установить модуль.
     *
     * @throws LoaderException
     */
    public static function validateModuleInstalled(string $moduleName): void
    {
        if (!Loader::includeModule($moduleName)
            && !Application::getInstance()->getContext()->getRequest()->isAdminSection()
        ) {
            throw new LoaderException(sprintf('Module "%s" is not installed!', $moduleName));
        }
    }

    /**
     * Компилирует ORM-сущности HL-блоков, чтобы скомпилированные классы
     * (например \RebitBalanceTable) были доступны для наследования в Table-классах модуля.
     *
     * @param array<int, string> $hlBlockNames
     */
    public static function compileHLEntities(array $hlBlockNames): void
    {
        foreach ($hlBlockNames as $name) {
            $hlBlock = HighloadBlockTable::getList([
                'filter' => ['NAME' => $name],
                'cache' => ['ttl' => 86400],
            ])->fetch();

            if (false !== $hlBlock) {
                HighloadBlockTable::compileEntity($hlBlock);
            }
        }
    }
}
