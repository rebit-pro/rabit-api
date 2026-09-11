<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Messenger;

/**
 * Базовый класс для всех асинхронных сообщений очереди.
 *
 * createdAt устанавливается автоматически при создании сообщения.
 * Ключ дедупликации по умолчанию — md5 от payload (все свойства кроме createdAt).
 */
abstract readonly class AbstractMessage
{
    public float $createdAt;

    public function __construct()
    {
        $this->createdAt = microtime(true);
    }

    /**
     * Ключ дедупликации сообщения.
     * По умолчанию — md5 от FQCN + payload.
     * Для кастомной логики — переопределить в дочернем классе.
     */
    public function getDeduplicationKey(): string
    {
        $vars = get_object_vars($this);
        unset($vars['createdAt']);

        return md5(static::class . serialize($vars));
    }
}
