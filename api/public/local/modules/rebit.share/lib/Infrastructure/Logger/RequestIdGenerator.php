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
}
