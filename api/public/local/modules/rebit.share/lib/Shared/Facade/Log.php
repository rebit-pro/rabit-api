<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Facade;

use Bitrix\Main\Config\Configuration;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Psr\Log\LoggerInterface;

/**
 * Фасад для битриксового monolog-логгера
 *
 * Использование:
 * 1. В легаси-коде как фасад
 * 2. В новых модулях только через DI: Log::getLogger(указать канал)
 *
 *  В .settings можно указать разные настройки для каждого из каналов, если указать в замыкание LogChannelEnum $channel
 *
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void emergency(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void log(string|\Stringable $level, string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 */
final class Log
{
    /** @var array<string, LoggerInterface> */
    private static array $channels = [];
    private static ?LoggerInterface $instance = null;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }

    public static function channel(LogChannelEnum $channel): LoggerInterface
    {
        return self::$channels[$channel->value] ??= self::buildChannelLogger($channel);
    }

    public static function getLogger(LogChannelEnum $default = LogChannelEnum::default): LoggerInterface
    {
        return self::$instance ??= self::buildChannelLogger($default);
    }

    public static function __callStatic(string $method, array $arguments)
    {
        return self::getLogger()->{$method}(...$arguments);
    }

    /**
     * Конфиг по умолчанию для логгера
     * Используется, если в .settings.php не определена секция "monolog"
     *
     * @return array<string, array{
     *     handler: callable(): AbstractProcessingHandler,
     *     formatter: callable(): FormatterInterface,
     *}>
     */
    private static function getDefaultConfig(): array
    {
        return [
            'stdout' => [
                'handler' => static fn() => new StreamHandler('php://stdout', Logger::INFO),
                'formatter' => static fn() => new LineFormatter(
                    allowInlineLineBreaks: true,
                    ignoreEmptyContextAndExtra: true,
                ),
            ],
        ];
    }

    /**
     * Создаёт логгер для указанного канала с уникальными настройками.
     */
    private static function buildChannelLogger(LogChannelEnum $channel): LoggerInterface
    {
        $configs = Configuration::getInstance()->get('monolog') ?? self::getDefaultConfig();
        $logger = new Logger($channel->value);

        foreach ($configs as $config) {
            /** @var AbstractProcessingHandler $handler */
            $handler = $config['handler']($channel);

            /** @var FormatterInterface $formatter */
            $formatter = $config['formatter']($channel);

            $handler->setFormatter($formatter);

            if (isset($config['processor']) && is_callable($config['processor'])) {
                $logger->pushProcessor(static fn(array $record) => $config['processor']($channel, $record));
            }

            $logger->pushHandler($handler);
        }

        return $logger;
    }
}
