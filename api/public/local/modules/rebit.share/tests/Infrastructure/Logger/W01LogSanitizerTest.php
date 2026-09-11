<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Logger\CommonLoggerProcessor;
use Rebit\Share\Infrastructure\Logger\LogSanitizer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;

/**
 * @internal
 */
final class W01LogSanitizerTest extends TestCase
{
    private const string REQUEST_ID = 'abcdef1234567.12345678';

    public function testFormattedRecordsPreserveDiagnosticMetadata(): void
    {
        $context = [
            'status' => 429,
            'method' => 'POST',
            'durationMs' => 12.345,
            'operation' => 'AuthController::confirmRegistrationAction',
            'requestId' => RequestIdGenerator::getRequestId(),
        ];
        $logs = $this->renderBoth('HTTP Response', $context, [
            'requestId' => self::REQUEST_ID,
            'durationMs' => 987654.0,
            'method' => 'DELETE',
        ]);
        $record = $this->decodeLogstash($logs['logstash']);

        self::assertSame('HTTP Response', $record['message']);
        self::assertEqualsCanonicalizing($context, $record['context']);
        self::assertSame(RequestIdGenerator::getRequestId(), $record['extra']['requestId']);
        self::assertNotSame(self::REQUEST_ID, $record['extra']['requestId']);
        self::assertSame('PATCH', $record['extra']['method']);
        self::assertGreaterThanOrEqual(0, $record['extra']['durationMs']);
        self::assertNotSame(987654.0, $record['extra']['durationMs']);
        self::assertStringContainsString('HTTP Response', $logs['line']);
        self::assertStringContainsString('"status":429', $logs['line']);
        self::assertStringContainsString('"durationMs":12.345', $logs['line']);
        self::assertStringContainsString('AuthController::confirmRegistrationAction', $logs['line']);
        self::assertStringContainsString(RequestIdGenerator::getRequestId(), $logs['line']);
    }

    public function testNeitherFormatterWritesSecretsFromBodiesHeadersErrorsOrDynamicKeys(): void
    {
        $secrets = [
            'W01-password-secret',
            'W01-confirmation-code-371942',
            'W01-bearer-token',
            'W01-order-key',
            'W01-url-token',
            'W01-response-secret',
            'W01-error-secret',
            'W01-secret-key-name',
        ];
        $payload = [
            'body' => ['account' => ['password' => $secrets[0], 'code' => $secrets[1]]],
            'headers' => [
                'Authorization' => 'Bearer ' . $secrets[2],
                'X-Order-Key' => $secrets[3],
            ],
            'url' => 'https://example.invalid/confirm?token=' . $secrets[4],
            'response' => ['data' => ['token' => $secrets[5]]],
            'errors' => [['message' => $secrets[6]]],
            $secrets[7] => 'untrusted-key',
        ];
        $context = ['status' => 401, 'operation' => 'auth.login', 'request' => $payload];
        $logs = $this->renderBoth('HTTP Error response', $context + $payload, $payload);

        foreach ($logs as $formatted) {
            foreach ($secrets as $secret) {
                self::assertStringNotContainsString($secret, $formatted);
            }
            self::assertStringNotContainsString('Bearer ', $formatted);
            self::assertStringNotContainsString('X-Order-Key', $formatted);
            self::assertStringNotContainsString('example.invalid', $formatted);
        }

        $record = $this->decodeLogstash($logs['logstash']);
        self::assertSame(401, $record['context']['status']);
        self::assertSame('auth.login', $record['context']['operation']);
        self::assertTrue($record['context']['redacted']);
        self::assertTrue($record['extra']['redacted']);
        foreach (array_keys($payload) as $key) {
            self::assertArrayNotHasKey($key, $record['context']);
            self::assertArrayNotHasKey($key, $record['extra']);
        }
    }

    public function testAllowedEventPrefixDoesNotPermitDynamicMessageText(): void
    {
        $logs = $this->renderBoth('HTTP Response W01-dynamic-message-secret', ['status' => 500]);

        foreach ($logs as $formatted) {
            self::assertStringNotContainsString('W01-dynamic-message-secret', $formatted);
            self::assertStringContainsString('[REDACTED]', $formatted);
        }
        self::assertSame('[REDACTED]', $this->decodeLogstash($logs['logstash'])['message']);
    }

    public function testExceptionMetadataDoesNotExposeMessagePreviousExceptionOrArguments(): void
    {
        $previousSetting = ini_set('zend.exception_ignore_args', '0');
        try {
            $exception = self::exceptionWithSensitiveArgument('W01-sensitive-trace-argument');
        } finally {
            if (false !== $previousSetting) {
                ini_set('zend.exception_ignore_args', $previousSetting);
            }
        }

        self::assertSame('W01-sensitive-trace-argument', $exception->getTrace()[0]['args'][0]);
        $metadata = (new LogSanitizer())->exception($exception);
        self::assertEqualsCanonicalizing([
            'class' => \RuntimeException::class,
            'file' => basename(__FILE__),
            'line' => $exception->getLine(),
            'exceptionCode' => 418,
        ], $metadata);
        $logs = $this->renderBoth('HTTP_EXCEPTION', $metadata + [
            'exception' => $exception,
            'trace' => $exception->getTrace(),
            'errors' => [$exception->getPrevious()],
        ], ['exception' => $exception]);

        foreach ($logs as $formatted) {
            foreach (['W01-exception-message', 'W01-previous-exception', 'W01-sensitive-trace-argument', __DIR__] as $secret) {
                self::assertStringNotContainsString($secret, $formatted);
            }
            self::assertStringContainsString(basename(__FILE__), $formatted);
            self::assertStringContainsString('RuntimeException', $formatted);
        }
        self::assertSame(418, $this->decodeLogstash($logs['logstash'])['context']['exceptionCode']);
    }

    public function testInvalidUtf8IsDroppedBeforeEitherFormatterEncodesTheRecord(): void
    {
        $invalid = "W01-invalid-utf8-secret\xC3\x28";
        $logs = $this->renderBoth('HTTP Request ' . $invalid, [
            'status' => 400,
            'operation' => $invalid,
            'request' => ['body' => $invalid],
            $invalid => 'W01-invalid-key-value',
        ], ['file' => $invalid, 'payload' => $invalid]);

        foreach ($logs as $formatted) {
            self::assertStringNotContainsString('W01-invalid', $formatted);
            self::assertStringNotContainsString("\xC3\x28", $formatted);
        }
        $record = $this->decodeLogstash($logs['logstash']);
        self::assertSame('[REDACTED]', $record['message']);
        self::assertSame(400, $record['context']['status']);
        self::assertArrayNotHasKey('operation', $record['context']);
        self::assertArrayNotHasKey('file', $record['extra']);
    }

    public function testRecursiveArraysAreDroppedWithoutWalkingTheirContents(): void
    {
        $recursive = ['password' => 'W01-recursive-secret'];
        $recursive['self'] = &$recursive;
        $logs = $this->renderBoth('REQUEST', [
            'status' => 202,
            'body' => $recursive,
        ], ['recursive' => $recursive]);

        foreach ($logs as $formatted) {
            self::assertStringNotContainsString('W01-recursive-secret', $formatted);
            self::assertLessThan(4096, strlen($formatted));
        }
        self::assertSame(202, $this->decodeLogstash($logs['logstash'])['context']['status']);
    }

    public function testObjectsAreNeverStringifiedIncludingValuesUnderAllowedKeys(): void
    {
        $object = new class implements \Stringable {
            public int $calls = 0;

            public function __toString(): string
            {
                ++$this->calls;

                throw new \LogicException('W01-stringable-secret');
            }
        };
        $logs = $this->renderBoth('REQUEST', [
            'operation' => $object,
            'method' => $object,
            'body' => $object,
        ], ['file' => $object, 'payload' => $object]);

        self::assertSame(0, $object->calls);
        foreach ($logs as $formatted) {
            self::assertStringNotContainsString('W01-stringable-secret', $formatted);
        }
        self::assertTrue($this->decodeLogstash($logs['logstash'])['context']['redacted']);
    }

    public function testLargeUntrustedContextCannotSuppressDiagnosticMetadataOrExpandTheLog(): void
    {
        $context = ['body' => str_repeat('W01-oversized-body-secret-', 65536)];
        for ($index = 0; 128 > $index; ++$index) {
            $context['W01-untrusted-key-' . $index] = 'W01-untrusted-value';
        }
        $context['status'] = 503;
        $context['method'] = 'POST';
        $context['durationMs'] = 25.5;
        $context['operation'] = 'auth.login';
        $context['requestId'] = RequestIdGenerator::getRequestId();
        $logs = $this->renderBoth('HTTP Request failed', $context);

        foreach ($logs as $formatted) {
            self::assertLessThan(4096, strlen($formatted));
            self::assertStringNotContainsString('W01-oversized-body-secret', $formatted);
            self::assertStringNotContainsString('W01-untrusted', $formatted);
        }
        $safe = $this->decodeLogstash($logs['logstash'])['context'];
        self::assertSame(503, $safe['status'] ?? null);
        self::assertSame('POST', $safe['method'] ?? null);
        self::assertSame(25.5, $safe['durationMs'] ?? null);
        self::assertSame('auth.login', $safe['operation'] ?? null);
        self::assertSame(RequestIdGenerator::getRequestId(), $safe['requestId'] ?? null);
    }

    public function testOversizedValuesUnderAllowedLabelKeysAreDroppedBeforeFormatting(): void
    {
        $label = str_repeat('W01-oversized-label-secret-', 20000);
        $logs = $this->renderBoth('HTTP Response', [
            'status' => 502,
            'operation' => $label,
        ], ['file' => $label]);

        foreach ($logs as $formatted) {
            self::assertLessThan(4096, strlen($formatted));
            self::assertStringNotContainsString('W01-oversized-label-secret', $formatted);
        }
        $record = $this->decodeLogstash($logs['logstash']);
        self::assertSame(502, $record['context']['status']);
        self::assertArrayNotHasKey('operation', $record['context']);
        self::assertArrayNotHasKey('file', $record['extra']);
    }

    public function testInvalidDiagnosticValuesCannotSmuggleFreeTextIntoLogs(): void
    {
        foreach ([INF, NAN, -1.0] as $duration) {
            $logs = $this->renderBoth('REQUEST', [
                'status' => 'W01-status-secret',
                'httpStatus' => 999,
                'durationMs' => $duration,
                'method' => 'POST W01-method-secret',
                'operation' => 'https://example.invalid/?token=W01-operation-secret',
                'requestId' => 'W01-request-id-secret',
                'file' => '/private/W01-path-secret.php',
            ]);
            foreach ($logs as $formatted) {
                self::assertStringNotContainsString('W01-', $formatted);
            }
            self::assertSame(['redacted' => true], $this->decodeLogstash($logs['logstash'])['context']);
        }
    }

    private static function exceptionWithSensitiveArgument(string $argument): \RuntimeException
    {
        return new \RuntimeException('W01-exception-message', 418, new \LogicException('W01-previous-exception'));
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $extra
     *
     * @return array{line: string, logstash: string}
     */
    private function renderBoth(string $message, array $context = [], array $extra = []): array
    {
        $hadMethod = array_key_exists('REQUEST_METHOD', $_SERVER);
        $hadTime = array_key_exists('REQUEST_TIME_FLOAT', $_SERVER);
        $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $previousTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 0.025;

        try {
            return [
                'line' => $this->render(new LineFormatter(allowInlineLineBreaks: true, ignoreEmptyContextAndExtra: true), $message, $context, $extra),
                'logstash' => $this->render(new LogstashFormatter('w01', 'test-host'), $message, $context, $extra),
            ];
        } finally {
            if ($hadMethod) {
                $_SERVER['REQUEST_METHOD'] = $previousMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            if ($hadTime) {
                $_SERVER['REQUEST_TIME_FLOAT'] = $previousTime;
            } else {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $extra
     */
    private function render(FormatterInterface $formatter, string $message, array $context, array $extra): string
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $handler = new StreamHandler($stream, Logger::DEBUG);
        $handler->setFormatter($formatter);
        $logger = new Logger('http', [$handler]);
        $logger->pushProcessor(static function(array $record) use ($extra): array {
            $record['extra'] = $extra;

            return (new CommonLoggerProcessor($record))();
        });

        try {
            $logger->warning($message, $context);
            rewind($stream);
            $formatted = stream_get_contents($stream);
            self::assertIsString($formatted);
            self::assertNotSame('', $formatted);

            return $formatted;
        } finally {
            $handler->close();
        }
    }

    /** @return array<string, mixed> */
    private function decodeLogstash(string $formatted): array
    {
        $record = json_decode($formatted, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        return $record;
    }
}
