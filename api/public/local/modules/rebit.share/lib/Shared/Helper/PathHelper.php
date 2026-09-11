<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Helper;

use Bitrix\Main\Application;

final readonly class PathHelper
{
    /**
     * Возвращает путь относительно корня сайта.
     *
     * Например: /var/bitrix_site/local/cron вернёт /local/cron
     */
    public static function getRelativePath(string $fullPath): string
    {
        return str_replace(Application::getDocumentRoot(), '', $fullPath);
    }
}
