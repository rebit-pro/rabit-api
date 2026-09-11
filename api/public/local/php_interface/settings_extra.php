<?php

declare(strict_types=1);

use Bitrix\Main\Diag\FileExceptionHandlerLog;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
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
                'class_name' => FileExceptionHandlerLog::class,
                'required_file' => '',
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
                    filePermission: 0644,
                ),
                'formatter' => static fn(LogChannelEnum $channel) => new LogstashFormatter($channel->value),
                'processor' => static function(LogChannelEnum $channel, array $record): array {
                    return (new CommonLoggerProcessor($record))();
                },
            ],
            'stdout' => [
                'handler' => static fn() => new StreamHandler('php://stdout', Logger::INFO),
                'formatter' => static fn() => new LineFormatter(
                    allowInlineLineBreaks: true,
                    ignoreEmptyContextAndExtra: true,
                ),
                'processor' => static function(LogChannelEnum $channel, array $record): array {
                    return (new CommonLoggerProcessor($record))();
                },
            ],
        ],
    ],
];
