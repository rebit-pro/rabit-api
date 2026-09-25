<?php

declare(strict_types=1);

namespace Rebit\Notification\Tests\Unit\Max;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Notification\Infrastructure\Max\MaxSendOutcomeClassifier;
use Rebit\Share\Application\Contract\Notification\Enum\MaxSendStatusEnum;

/**
 * @internal
 */
final class MaxSendOutcomeClassifierTest extends TestCase
{
    public function testAcceptedMessageReturnsItsMid(): void
    {
        $outcome = (new MaxSendOutcomeClassifier())->classify(0, 200, '{"message":{"body":{"mid":"mid.00000000000000a1","seq":1},"timestamp":1}}');

        self::assertSame(MaxSendStatusEnum::DELIVERED, $outcome->status);
        self::assertSame('mid.00000000000000a1', $outcome->mid);
    }

    #[DataProvider('outcomes')]
    public function testOnlyRequestsThatSurelyWereNotProcessedAreRetried(int $curlError, int $status, ?string $body, MaxSendStatusEnum $expected, string $code): void
    {
        $outcome = (new MaxSendOutcomeClassifier())->classify($curlError, $status, $body);

        self::assertSame($expected, $outcome->status);
        self::assertSame($code, $outcome->errorCode);
        self::assertNull($outcome->mid);
    }

    /** @return iterable<string, array{int, int, ?string, MaxSendStatusEnum, string}> */
    public static function outcomes(): iterable
    {
        yield 'connection refused before sending' => [\CURLE_COULDNT_CONNECT, 0, null, MaxSendStatusEnum::RETRY, 'max_connect_failed'];
        yield 'dns failure' => [\CURLE_COULDNT_RESOLVE_HOST, 0, null, MaxSendStatusEnum::RETRY, 'max_connect_failed'];
        yield 'timeout after sending' => [\CURLE_OPERATION_TIMEDOUT, 0, null, MaxSendStatusEnum::UNKNOWN, 'max_transport_error'];
        yield 'rate limit' => [0, 429, '{}', MaxSendStatusEnum::RETRY, 'max_http_429'];
        yield 'gateway timeout may hide a created message' => [0, 504, null, MaxSendStatusEnum::UNKNOWN, 'max_http_504'];
        yield 'bad gateway may hide a created message' => [0, 502, null, MaxSendStatusEnum::UNKNOWN, 'max_http_502'];
        yield 'service unavailable is not proof of no message' => [0, 503, null, MaxSendStatusEnum::UNKNOWN, 'max_http_503'];
        yield 'server error may have stored the message' => [0, 500, null, MaxSendStatusEnum::UNKNOWN, 'max_http_500'];
        yield 'invalid token' => [0, 401, '{"code":"verify.token"}', MaxSendStatusEnum::REJECTED, 'max_http_401'];
        yield 'bot removed from chat' => [0, 403, '{}', MaxSendStatusEnum::REJECTED, 'max_http_403'];
        yield 'accepted without mid' => [0, 200, '{"message":{}}', MaxSendStatusEnum::UNKNOWN, 'max_response_invalid'];
        yield 'accepted with broken json' => [0, 200, '{', MaxSendStatusEnum::UNKNOWN, 'max_response_invalid'];
    }
}
