<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Messenger;

/**
 * Порт для отправки сообщений в очередь.
 */
interface MessagePublisherInterface
{
    /**
     * @param int $deduplicateTime окно дедупликации в секундах (0 — без дедупликации)
     */
    public function dispatch(AbstractMessage $message, int $deduplicateTime = 0): void;
}
