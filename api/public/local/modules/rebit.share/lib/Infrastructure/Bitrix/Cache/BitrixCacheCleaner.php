<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Bitrix\Cache;

use Bitrix\Main\Application;
use Rebit\Share\Application\Contract\Cache\CacheCleanerInterface;

final class BitrixCacheCleaner implements CacheCleanerInterface
{
    public function cleanManaged(string $cacheId): void
    {
        Application::getInstance()->getManagedCache()->clean($cacheId);
    }

    public function cleanDir($initDir = false, $baseDir = 'cache'): void
    {
        Application::getInstance()->getCache()->cleanDir($initDir, $baseDir);
    }

    /**
     * {@inheritDoc}
     */
    public function cleanTagged(array $tags): void
    {
        foreach ($tags as $tag) {
            Application::getInstance()->getTaggedCache()->clearByTag($tag);
        }
    }
}
