<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\Cache\KeyValueEngine;

/**
 * Реализация DedupCacheInterface на основе Bitrix managed_cache.
 *
 * При наличии KeyValueEngine (Redis/APCu) — атомарная дедупликация через setNotExists.
 * Fallback через managed_cache — best-effort: при параллельных publisher'ах
 * возможна гонка (read/set не атомарны), что допустимо для идемпотентных handler'ов.
 */
final readonly class BitrixDedupCache implements DedupCacheInterface
{
    public function claim(string $key, int $ttl): bool
    {
        $cacheEngine = Cache::createCacheEngine();

        if ($cacheEngine instanceof KeyValueEngine) {
            return $cacheEngine->setNotExists($key, $ttl, true);
        }

        $managedCache = Application::getInstance()->getManagedCache();
        if ($managedCache->read($ttl, $key)) {
            return false;
        }

        $managedCache->set($key, true);

        return true;
    }

    public function release(string $key): void
    {
        $cacheEngine = Cache::createCacheEngine();

        if ($cacheEngine instanceof KeyValueEngine) {
            $cacheEngine->del($key);

            return;
        }

        Application::getInstance()->getManagedCache()->clean($key);
    }
}
