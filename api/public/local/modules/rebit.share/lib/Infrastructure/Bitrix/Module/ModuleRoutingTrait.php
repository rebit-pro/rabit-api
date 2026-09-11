<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Bitrix\Module;

use Bitrix\Main\Config\Configuration;
use Bitrix\Main\InvalidOperationException;
use Bitrix\Main\SystemException;
use Rebit\Share\Shared\Facade\Log;

/**
 * Трейт добавляет поддержку роутинга в модуль.
 *
 * В корне модуля должен лежать файл routing.php c описанием маршрутов согласно документации битрикса.
 * При установке будет прокинут symlink на routing.php с именем модуля в папку /local/routes/,
 * а так же зарегистрирован в конфигурации .setting.php через API Configuration битрикса (файл будет пересоздан).
 *
 * Используйте:
 *      initModuleRouting() - в конструкторе модуля для инициализации
 *      installModuleRouting() - для установки роутинга
 *      uninstallModuleRouting() - для удаления роутинга
 */
trait ModuleRoutingTrait
{
    /** Название файла симлинка (генерируется автоматически) */
    private ?string $routeFileName = null;

    /** Полный путь к симлинку (генерируется автоматически) */
    private ?string $routeFilePath = null;

    /** Полный путь к файлу роутинга в модуле (генерируется автоматически) */
    private ?string $moduleRouteFilePath = null;

    /**
     * Добавляет маршрут в конфигурацию Битрикс
     *
     * @throws InvalidOperationException|SystemException
     */
    private function addModuleRoutes(): void
    {
        if (null === $this->routeFileName) {
            throw new SystemException('Роутинг не инициализирован, вызовите initModuleRouting в конструкторе!');
        }

        $config = Configuration::getInstance()->get('routing') ?? ['config' => []];

        if ($this->isRoutingPresent($config['config'])) {
            return;
        }

        $config['config'][] = $this->routeFileName;
        $this->saveRoutingConfig($config);
    }

    /**
     * Удаляет маршрут из конфигурации Битрикс
     *
     * @throws InvalidOperationException
     */
    private function removeModuleRoute(): void
    {
        $currentRouting = Configuration::getInstance()->get('routing');
        $currentConfig = $currentRouting['config'] ?? [];

        if (!$this->isRoutingPresent($currentConfig)) {
            return;
        }

        $key = array_search($this->routeFileName, $currentConfig, true);
        if (false === $key) {
            return;
        }

        unset($currentRouting['config'][$key]);
        $this->saveRoutingConfig($currentRouting);
    }

    /**
     * Создает симлинк для маршрутов модуля
     *
     * @throws SystemException
     */
    private function createRouteSymlink(): void
    {
        if (null === $this->routeFileName) {
            throw new SystemException('Роутинг не инициализирован, вызовите initModuleRouting в конструкторе!');
        }

        if (!symlink($this->moduleRouteFilePath, $this->routeFilePath)) {
            throw new SystemException("Не удалось создать симлинк {$this->routeFilePath}");
        }
    }

    /**
     * Удаляет симлинк маршрутов модуля
     *
     * @throws SystemException
     */
    private function removeRouteSymlink(): void
    {
        if (!is_link($this->routeFilePath)) {
            return;
        }

        if (!unlink($this->routeFilePath)) {
            Log::error("Не удалось удалить симлинк {$this->routeFilePath}");
        }
    }

    /**
     * Сохраняет конфигурацию роутинга
     *
     * @throws InvalidOperationException
     */
    private function saveRoutingConfig(array $config): void
    {
        $configuration = Configuration::getInstance();
        $configuration->add('routing', $config);
        $configuration->saveConfiguration();
    }

    /**
     * @param string[] $routingConfig
     */
    private function isRoutingPresent(array $routingConfig): bool
    {
        return in_array($this->routeFileName, $routingConfig, true);
    }

    /**
     * Инициализирует пути для работы с роутингом
     * Должен вызываться в конструкторе модуля!
     *
     * @throws SystemException
     */
    final public function initModuleRouting(): void
    {
        $this->moduleRouteFilePath = $_SERVER['DOCUMENT_ROOT']
            . getLocalPath("modules/{$this->MODULE_ID}/routes.php");

        if (!file_exists($this->moduleRouteFilePath)) {
            throw new SystemException('Файл routes.php модуля не обнаружен!');
        }

        $this->routeFileName = $this->MODULE_ID . '.php';
        $this->routeFilePath = $_SERVER['DOCUMENT_ROOT'] . '/local/routes/' . $this->routeFileName;
    }

    /**
     * @throws SystemException
     * @throws InvalidOperationException
     */
    final public function installModuleRouting(): void
    {
        $this->createRouteSymlink();
        $this->addModuleRoutes();
    }

    /**
     * @throws InvalidOperationException
     * @throws SystemException
     */
    final public function uninstallModuleRouting(): void
    {
        $this->removeRouteSymlink();
        $this->removeModuleRoute();
    }
}
