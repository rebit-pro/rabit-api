<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
use Rebit\Share\Infrastructure\Logger\HttpDebugLoggerFactory;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;

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

    public function testConfiguredProcessorIsSanitizedBeforeBufferedRecordsAreFlushed(): void
    {
        $handler = new TestHandler(Logger::DEBUG);
        $handler->setFormatter(new LineFormatter());
        $logger = new Logger('http', [$handler]);
        $logger->pushProcessor(static fn(array $record): array => (new CommonLoggerProcessor($record))());
        $logger->pushProcessor(static function(array $record): array {
            $record['message'] = 'W01_BUFFER_SECRET_MESSAGE';
            $record['context']['body'] = ['password' => 'W01_BUFFER_SECRET_PASSWORD'];
            $record['context']['operation'] = self::class . '::request';
            $record['context']['method'] = 'POST';
            $record['context']['httpStatus'] = 503;
            $record['context']['durationMs'] = 12.5;
            $record['context']['requestId'] = RequestIdGenerator::getRequestId();
            $record['extra']['authorization'] = 'Bearer W01_BUFFER_SECRET_TOKEN';

            return $record;
        });
        $bufferedLogger = HttpDebugLoggerFactory::create($logger);

        $bufferedLogger->debug('HTTP Request');
        self::assertSame([], $handler->getRecords());
        $bufferedLogger->warning('HTTP Request failed');

        $records = $handler->getRecords();
        self::assertCount(2, $records);
        foreach ($records as $record) {
            self::assertIsString($record['formatted']);
            self::assertStringNotContainsString('W01_BUFFER_SECRET_', $record['formatted']);
            self::assertArrayNotHasKey('body', $record['context']);
            self::assertArrayNotHasKey('authorization', $record['extra']);
            self::assertSame(self::class . '::request', $record['context']['operation']);
            self::assertSame('POST', $record['context']['method']);
            self::assertSame(503, $record['context']['httpStatus']);
            self::assertSame(12.5, $record['context']['durationMs']);
            self::assertSame(RequestIdGenerator::getRequestId(), $record['context']['requestId']);
            self::assertSame(RequestIdGenerator::getRequestId(), $record['extra']['requestId']);
        }
    }

    public function testNonMonologLoggerIsReturnedAsIs(): void
    {
        $logger = new class extends AbstractLogger {
            public function log(mixed $level, string|\Stringable $message, array $context = []): void {}
        };

        self::assertSame($logger, HttpDebugLoggerFactory::create($logger));
    }
}
