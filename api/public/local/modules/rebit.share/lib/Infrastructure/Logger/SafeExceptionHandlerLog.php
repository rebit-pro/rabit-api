<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

use Bitrix\Main\Diag\FileExceptionHandlerLog;
use Bitrix\Main\Diag\FileLogger;
use Bitrix\Main\Diag\LogFormatterInterface;

/**
 * Bitrix creates this adapter before Composer and module bootstrap are available.
 */
final class SafeExceptionHandlerLog extends FileExceptionHandlerLog
{
    /** @param array<string, mixed> $options */
    public function initialize(array $options): void
    {
        parent::initialize($options);

        // Keep the kernel's file locking and rotation, without placeholder expansion.
        if ($this->logger instanceof FileLogger) {
            $this->logger->setFormatter(new class implements LogFormatterInterface {
                /** @param array<string, mixed> $context */
                public function format(mixed $message, array $context = []): string
                {
                    return is_string($message) ? $message : '';
                }
            });
        }
    }

    public function write(mixed $exception, mixed $logType): void
    {
        if (!$exception instanceof \Throwable) {
            return;
        }

        $type = is_int($logType) ? $logType : self::CAUGHT_EXCEPTION;
        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'message' => 'PHP exception',
            'type' => self::logTypeToString($type),
            'requestId' => RequestIdGenerator::getRequestId(),
            'durationMs' => RequestIdGenerator::getDurationMs(),
            'exception' => (new LogSanitizer())->exception($exception),
        ];

        $this->logger->log(
            self::logTypeToLevel($type),
            json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );
    }
}
