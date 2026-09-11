<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Bitrix\Module;

use Bitrix\Main\SystemException;

/**
 * @property string $MODULE_ID
 */
trait ModuleComponentInstallerTrait
{
    private const string COMPONENTS_PATH = '/bitrix/components/rebit';

    private ?string $componentsPath = null;

    protected function initModuleComponentInstaller(): void
    {
        $this->componentsPath = $_SERVER['DOCUMENT_ROOT']
            . getLocalPath("modules/{$this->MODULE_ID}/install/components");
    }

    /**
     * @throws SystemException
     */
    protected function installComponents(): void
    {
        $componentsPath = $this->getInitializedComponentsPath();

        if (!is_dir($componentsPath)) {
            return;
        }

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . self::COMPONENTS_PATH;

        if (!CopyDirFiles(
            path_from: $componentsPath,
            path_to: $fullPath,
            Recursive: true,
        )) {
            throw new SystemException('Не удалось скопировать компоненты модуля');
        }
    }

    /**
     * @throws SystemException
     */
    protected function uninstallComponents(): void
    {
        $componentsPath = $this->getInitializedComponentsPath();

        if (!is_dir($componentsPath)) {
            return;
        }

        $componentsBitrixRoot = $_SERVER['DOCUMENT_ROOT'] . self::COMPONENTS_PATH;

        if (!is_dir($componentsBitrixRoot)) {
            return;
        }

        foreach ($this->getComponentNames($componentsPath) as $componentName) {
            $this->uninstallComponent($componentName);
        }
    }

    /**
     * @throws SystemException
     */
    private function uninstallComponent(string $componentName): void
    {
        $relativePath = self::COMPONENTS_PATH . '/' . $componentName;
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;

        if (!is_dir($absolutePath)) {
            return;
        }

        if (!DeleteDirFilesEx($relativePath)) {
            throw new SystemException(
                "Не удалось удалить компонент: {$componentName}",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function getComponentNames(string $path): array
    {
        $components = [];

        /** @var \DirectoryIterator $item */
        foreach (new \DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $components[] = $item->getFilename();
        }

        return $components;
    }

    /**
     * @throws SystemException
     */
    private function getInitializedComponentsPath(): string
    {
        if (null === $this->componentsPath) {
            throw new SystemException(
                'Установщики компонентов не инициализированы, вызовите initModuleComponentInstaller в конструкторе!',
            );
        }

        return $this->componentsPath;
    }
}
