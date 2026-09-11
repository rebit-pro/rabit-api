<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Logger;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Rebit\Share\Infrastructure\Logger\HttpDebugLoggerFactory;

/**
 * @internal
 */
final class HttpDebugLoggerFactoryTest extends TestCase
{
    public function testDebugIsNotWrittenWithoutWarningOrError(): void
    {
        $handler = new TestHandler(Logger::DEBUG);
        $bufferedLogger = HttpDebugLoggerFactory::create(new Logger('http', [$handler]));

        $bufferedLogger->debug('HTTP Request', ['url' => '/ping']);

        self::assertFalse($handler->hasDebugRecords());
    }

    public function testBufferedDebugIsFlushedWhenWarningHappens(): void
    {
        $handler = new TestHandler(Logger::DEBUG);
        $bufferedLogger = HttpDebugLoggerFactory::create(new Logger('http', [$handler]));

        $bufferedLogger->debug('HTTP Request', ['url' => '/ping']);
        $bufferedLogger->warning('HTTP Warning', ['status' => 429]);

        self::assertTrue($handler->hasDebugRecords());
        self::assertTrue($handler->hasWarningRecords());
    }

    public function testBufferedDebugIsFlushedEvenIfUnderlyingHandlerLevelIsInfo(): void
    {
        $handler = new TestHandler(Logger::INFO);
        $bufferedLogger = HttpDebugLoggerFactory::create(new Logger('http', [$handler]));

        $bufferedLogger->debug('HTTP Request', ['url' => '/ping']);
        $bufferedLogger->warning('HTTP Warning', ['status' => 429]);

        self::assertTrue($handler->hasDebug([
            'message' => 'HTTP Request',
            'context' => ['url' => '/ping'],
        ]));
        self::assertTrue($handler->hasWarningRecords());
    }

    public function testLoggerStartsBufferingAgainAfterWarning(): void
    {
        $handler = new TestHandler(Logger::DEBUG);
        $bufferedLogger = HttpDebugLoggerFactory::create(new Logger('http', [$handler]));

        $bufferedLogger->debug('HTTP Request', ['url' => '/first']);
        $bufferedLogger->warning('HTTP Warning', ['status' => 429]);

        self::assertTrue($handler->hasDebug([
            'message' => 'HTTP Request',
            'context' => ['url' => '/first'],
        ]));

        $handler->clear();

        $bufferedLogger->debug('HTTP Request', ['url' => '/second']);

        self::assertFalse($handler->hasDebug([
            'message' => 'HTTP Request',
            'context' => ['url' => '/second'],
        ]));
    }

    public function testNonMonologLoggerIsReturnedAsIs(): void
    {
        $logger = new class extends AbstractLogger {
            public function log(mixed $level, string|\Stringable $message, array $context = []): void {}
        };

        self::assertSame($logger, HttpDebugLoggerFactory::create($logger));
    }
}
