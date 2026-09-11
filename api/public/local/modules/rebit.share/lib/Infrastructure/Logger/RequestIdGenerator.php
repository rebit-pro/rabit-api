<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

/**
 * Класс генерирует уникальный идентификатор для каждого запроса или консольного выполнения.
 */
final class RequestIdGenerator
{
    private static ?string $requestId = null;

    public static function getRequestId(): string
    {
        if (null === self::$requestId) {
            self::$requestId = uniqid('', true);
        }

        return self::$requestId;
    }

    /** Elapsed time since PHP accepted the request; shared by separate filters. */
    public static function getDurationMs(): float
    {
        $now = microtime(true);
        $startedAt = $_SERVER['REQUEST_TIME_FLOAT'] ?? $now;

        return round(max(0.0, $now - (float)$startedAt) * 1000, 3);
    }
}
