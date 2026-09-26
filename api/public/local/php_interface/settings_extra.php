<?php

declare(strict_types=1);

use Rebit\Share\Infrastructure\Logger\SafeExceptionHandlerLog;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rebit\Share\Shared\Enum\LogChannelEnum;

return [
    'routing' => [
        'value' => [
            'config' => ['rabit-api.php'],
        ],
        'readonly' => true,
    ],
    'exception_handling' => [
        'value' => [
            'debug' => '1' === ($_ENV['APP_DEBUG'] ?? '0'),
            'handled_errors_types' => 4437,
            'exception_errors_types' => 4437,
            'ignore_silence' => false,
            'assertion_throws_exception' => true,
            'assertion_error_type' => 256,
            'log' => [
                'class_name' => SafeExceptionHandlerLog::class,
                'required_file' => 'php_interface/safe-exception-log.php',
                'settings' => [
                    'file' => dirname(__DIR__, 3) . '/logs/bx_error.log',
                    'log_size' => 1000000,
                ],
            ],
        ],
        'readonly' => false,
    ],
    'monolog' => [
        'value' => [
            'logstash' => [
                'handler' => static fn(LogChannelEnum $channel) => new RotatingFileHandler(
                    filename: dirname(__DIR__, 3) . '/logs/logstash/' . $channel->value . '.log',
                    maxFiles: 5,
                    level: Logger::INFO,
                    // #141: суточный файл канала может создать root (cron), а дописывать — www-data (FPM, медиа-воркер).
                    filePermission: 0664,
                ),
                'formatter' => static fn(LogChannelEnum $channel) => new LogstashFormatter($channel->value),
            ],
            'stdout' => [
                'handler' => static fn() => new StreamHandler('php://stdout', Logger::INFO),
                'formatter' => static fn() => new LineFormatter(
                    allowInlineLineBreaks: true,
                    ignoreEmptyContextAndExtra: true,
                ),
            ],
        ],
    ],
];
