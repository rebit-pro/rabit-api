<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Logger;

use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Rebit\Share\Infrastructure\Logger\Handler\NonClosingGroupHandler;

final class HttpDebugLoggerFactory
{
    public static function create(LoggerInterface $logger): LoggerInterface
    {
        if (!$logger instanceof Logger) {
            return $logger;
        }

        $handlers = $logger->getHandlers();
        if ([] === $handlers) {
            return $logger;
        }

        $bufferedLogger = new Logger($logger->getName());

        foreach ($logger->getProcessors() as $processor) {
            $bufferedLogger->pushProcessor($processor);
        }

        $bufferedLogger->pushHandler(
            new FingersCrossedHandler(
                handler: self::buildHandler($handlers),
                activationStrategy: Logger::WARNING,
                stopBuffering: false,
            ),
        );

        return $bufferedLogger;
    }

    /**
     * @param array<int, HandlerInterface> $handlers
     */
    private static function buildHandler(array $handlers): HandlerInterface
    {
        return new NonClosingGroupHandler($handlers);
    }
}
