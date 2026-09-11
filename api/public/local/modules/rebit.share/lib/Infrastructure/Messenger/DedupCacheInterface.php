<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Messenger;

/**
 * Кеш для дедупликации сообщений.
 *
 * Контракт: claim() вызывается для ключа; возвращает true, если ключ был
 * зарезервирован успешно (т.е. сообщение можно отправлять).
 */
interface DedupCacheInterface
{
    /**
     * Атомарно пытается зарезервировать ключ на TTL.
     *
     * Возвращает true, если резервирование прошло успешно и сообщение можно отправлять.
     */
    public function claim(string $key, int $ttl): bool;

    /**
     * Снимает резервирование ключа (rollback при ошибке dispatch).
     */
    public function release(string $key): void;
}
